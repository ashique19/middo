<?php

namespace Database\Seeders;

use App\Models\KitchenSettlementBatch;
use App\Models\KitchenWithdrawalRequest;
use App\Models\MenuItem;
use App\Models\MiddoBankAccount;
use App\Models\MiddoBankLedgerEntry;
use App\Models\MiddoOperatingCost;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\PartnerPayable;
use App\Models\RiderWithdrawalRequest;
use App\Models\User;
use App\Support\KitchenAccountLedger;
use App\Support\KitchenMoneyService;
use App\Support\MiddoBankLedger;
use App\Support\MiddoCashLedger;
use App\Support\MiddoSettings;
use App\Support\PayoutChannel;
use App\Support\RiderAccountLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Demo fixtures for accounts finance stretch F0a–F0e + F2.
 *
 * Run after base DatabaseSeeder (users/menus required):
 *   php artisan db:seed --class=AccountsFinanceTestSeeder
 *
 * Logins (password 12345678):
 *   accounts@middo.com / 01310123462
 *   admin@middo.com / 01310123451
 *   kitchen@middo.com / 01310123453
 *   delivery@middo.com / 01310123454
 */
class AccountsFinanceTestSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = User::query()->where('email', 'accounts@middo.com')->orWhere('mobile', '01310123462')->first();
        $admin = User::query()->where('email', 'admin@middo.com')->orWhere('mobile', '01310123451')->first();
        $kitchen = User::query()->where('email', 'kitchen@middo.com')->orWhere('mobile', '01310123453')->first();
        $rider = User::query()->where('email', 'delivery@middo.com')->orWhere('mobile', '01310123454')->first();
        $corporate = User::query()->where('email', 'corporate@middo.com')->orWhere('mobile', '01310123452')->first();
        $menus = MenuItem::query()->orderBy('display_order')->get();
        $actor = $accounts ?? $admin;

        if (! $kitchen || ! $corporate || ! $actor || $menus->isEmpty()) {
            $this->command?->warn('AccountsFinanceTestSeeder skipped: need kitchen, corporate, accounts|admin, and menus.');

            return;
        }

        MiddoSettings::updateMealAndKitchenDefaults([
            'vat_rate_pct' => 5,
            'eps_fee_rates' => [
                'bank' => 1.5,
                'bkash' => 1.8,
                'nagad' => 1.8,
            ],
        ]);

        $bank = $this->seedBankAccounts($actor);
        $this->seedTillAndBankFloat($bank, $actor);
        $this->seedPeriodOrders($kitchen, $corporate, $menus->first(), $actor);
        $this->seedOperatingCost($rider, $actor);
        $this->seedChannelWithdrawals($kitchen, $rider, $actor);
        $this->seedSettlementBatch($kitchen, $corporate, $menus->first(), $actor);

        $this->command?->info('AccountsFinanceTestSeeder: VAT settings, banks, till, EPS fees, P&L orders, withdrawals, settlement batch ready.');
        $this->command?->info('  Accounts: accounts@middo.com / 01310123462 / 12345678');
        $this->command?->info('  Surfaces: Period P&L · Cash positions · Bank ledger · Kitchen money batches');
    }

    protected function seedBankAccounts(User $actor): MiddoBankAccount
    {
        if (! Schema::hasTable('middo_bank_accounts')) {
            throw new \RuntimeException('middo_bank_accounts table missing — run migrations.');
        }

        $primary = MiddoBankAccount::query()->updateOrCreate(
            ['name' => 'Ops BRAC'],
            [
                'bank_name' => 'BRAC Bank',
                'account_number' => '1501200012345',
                'branch' => 'Gulshan',
                'is_default' => true,
                'is_active' => true,
                'notes' => 'Default EPS settlement account (AccountsFinanceTestSeeder)',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        MiddoBankAccount::query()->updateOrCreate(
            ['name' => 'Ops City'],
            [
                'bank_name' => 'City Bank',
                'account_number' => '1402600098765',
                'branch' => 'Banani',
                'is_default' => false,
                'is_active' => true,
                'notes' => 'Secondary float (AccountsFinanceTestSeeder)',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        MiddoSettings::updateMealAndKitchenDefaults([
            'default_eps_bank_account_id' => (int) $primary->id,
        ]);

        return $primary;
    }

    protected function seedTillAndBankFloat(MiddoBankAccount $bank, User $actor): void
    {
        if (Schema::hasTable('middo_cash_ledger') && MiddoCashLedger::balance() < 5000) {
            MiddoCashLedger::credit(
                10000 - MiddoCashLedger::balance(),
                'seed',
                null,
                null,
                'Demo till seed (AccountsFinanceTestSeeder)',
                $actor->id
            );
        }

        if (! Schema::hasTable('middo_bank_ledger')) {
            return;
        }

        if (MiddoBankLedger::balance((int) $bank->id) < 1) {
            MiddoBankLedger::credit(
                (int) $bank->id,
                5000,
                MiddoBankLedgerEntry::TYPE_ADJUSTMENT,
                null,
                null,
                'Opening bank float (AccountsFinanceTestSeeder)',
                $actor->id
            );

            MiddoBankLedger::credit(
                (int) $bank->id,
                982,
                MiddoBankLedgerEntry::TYPE_EPS_IN_NET,
                null,
                null,
                'Demo EPS net credit (AccountsFinanceTestSeeder)',
                $actor->id,
                [
                    'gross_amount' => 1000,
                    'fee_amount' => 18,
                    'sub_gateway' => 'bank',
                    'gateway_token' => 'seed-eps-'.now()->format('Ymd'),
                ]
            );
        }
    }

    protected function seedPeriodOrders(User $kitchen, User $corporate, MenuItem $menu, User $actor): void
    {
        if (Order::query()->where('address', 'AccountsFinance P&L demo')->exists()) {
            return;
        }

        // Ensure kitchen commission for middo_rest demo
        if ((int) $menu->kitchen_commission < 1) {
            $menu->update([
                'kitchen_commission' => max(50, (int) round(((int) $menu->price) * 0.25)),
                'delivery_commission' => max(20, (int) ($menu->delivery_commission ?: 30)),
            ]);
            $menu->refresh();
        }

        $today = now('Asia/Dhaka')->toDateString();

        for ($i = 0; $i < 2; $i++) {
            $order = Order::create([
                'user_id' => $corporate->id,
                'menu_item_id' => $menu->id,
                'quantity' => 1,
                'delivery_date' => $today,
                'delivery_time' => '12:00 PM',
                'total_amount' => (int) $menu->price,
                'amount_paid' => (int) $menu->price,
                'address' => 'AccountsFinance P&L demo',
                'order_status' => 'pending',
                'payment_status' => 'paid',
                'payment_method' => 'cash_on_delivery',
                'created_by' => $corporate->id,
                'updated_by' => $corporate->id,
            ]);

            $group = OrderGroup::create([
                'name' => 'GRP-AFIN-'.$i.'-'.uniqid(),
                'menu_id' => $menu->id,
                'delivery_date' => $today,
                'kitchen_id' => $kitchen->id,
            ]);
            $group->orders()->attach($order->id);

            $order->update([
                'dispatched_at' => now(),
                'order_status' => 'delivered_and_paid',
            ]);
        }
    }

    protected function seedOperatingCost(?User $rider, User $actor): void
    {
        if (! $rider || ! Schema::hasTable('middo_operating_costs')) {
            return;
        }

        if (MiddoOperatingCost::query()->where('description', 'like', '%AccountsFinanceTestSeeder%')->exists()) {
            return;
        }

        MiddoOperatingCost::create([
            'cost_type' => MiddoOperatingCost::TYPE_RIDER_COMMISSION,
            'amount' => 40,
            'run_type' => 'box',
            'rider_user_id' => $rider->id,
            'description' => 'Demo box commission (AccountsFinanceTestSeeder)',
            'created_by' => $actor->id,
        ]);
    }

    protected function seedChannelWithdrawals(User $kitchen, ?User $rider, User $actor): void
    {
        if (Schema::hasTable('kitchen_withdrawal_requests')
            && ! KitchenWithdrawalRequest::query()
                ->where('kitchen_user_id', $kitchen->id)
                ->where('payout_channel', PayoutChannel::BANK)
                ->where('status', KitchenWithdrawalRequest::STATUS_PENDING)
                ->exists()) {
            if (KitchenAccountLedger::balance($kitchen->id) < 200) {
                KitchenAccountLedger::credit(
                    $kitchen->id,
                    500,
                    'share_accrued',
                    null,
                    null,
                    'Extra wallet for bank withdrawal demo (AccountsFinanceTestSeeder)',
                    $actor->id
                );
            }

            KitchenWithdrawalRequest::create([
                'kitchen_user_id' => $kitchen->id,
                'amount' => 200,
                'status' => KitchenWithdrawalRequest::STATUS_PENDING,
                'notes' => 'Demo bank-channel withdrawal (AccountsFinanceTestSeeder)',
                'payout_channel' => PayoutChannel::BANK,
                'payout_details' => [
                    'bank_name' => 'Dutch Bangla',
                    'account_name' => 'Gulshan Kitchen',
                    'account_number' => '2051100012345',
                ],
            ]);
        }

        if ($rider && Schema::hasTable('rider_withdrawal_requests')
            && ! RiderWithdrawalRequest::query()
                ->where('rider_user_id', $rider->id)
                ->where('payout_channel', PayoutChannel::BKASH)
                ->where('status', RiderWithdrawalRequest::STATUS_PENDING)
                ->exists()) {
            if (RiderAccountLedger::balance($rider->id) < 100) {
                RiderAccountLedger::credit(
                    $rider->id,
                    150,
                    'commission_accrued',
                    null,
                    null,
                    'Wallet for bKash withdrawal demo (AccountsFinanceTestSeeder)',
                    $actor->id
                );
            }

            // Clear Due so payment request is approvable in UI previews
            if ((int) $rider->balance > 0) {
                $rider->update(['balance' => 0]);
            }

            RiderWithdrawalRequest::create([
                'rider_user_id' => $rider->id,
                'amount' => 100,
                'status' => RiderWithdrawalRequest::STATUS_PENDING,
                'notes' => 'Demo bKash withdrawal (AccountsFinanceTestSeeder)',
                'payout_channel' => PayoutChannel::BKASH,
                'payout_details' => [
                    'account_name' => 'Demo Rider',
                    'mobile' => '01711112222',
                ],
            ]);
        }
    }

    protected function seedSettlementBatch(User $kitchen, User $corporate, MenuItem $menu, User $actor): void
    {
        if (! Schema::hasTable('kitchen_settlement_batches')) {
            return;
        }

        if (KitchenSettlementBatch::query()->where('name', 'Demo week remittance')->exists()) {
            return;
        }

        $payableIds = [];
        for ($i = 0; $i < 2; $i++) {
            $order = Order::create([
                'user_id' => $corporate->id,
                'menu_item_id' => $menu->id,
                'quantity' => 1,
                'delivery_date' => now('Asia/Dhaka')->toDateString(),
                'delivery_time' => '1:00 PM',
                'total_amount' => (int) $menu->price,
                'amount_paid' => (int) $menu->price,
                'address' => 'AccountsFinance batch demo',
                'order_status' => 'pending',
                'payment_status' => 'paid',
                'payment_method' => 'cash_on_delivery',
                'created_by' => $corporate->id,
                'updated_by' => $corporate->id,
            ]);

            $group = OrderGroup::create([
                'name' => 'GRP-AFIN-BATCH-'.$i.'-'.uniqid(),
                'menu_id' => $menu->id,
                'delivery_date' => now('Asia/Dhaka')->toDateString(),
                'kitchen_id' => $kitchen->id,
            ]);
            $group->orders()->attach($order->id);

            $order->update([
                'dispatched_at' => now(),
                'order_status' => 'delivered_and_paid',
            ]);

            $payable = PartnerPayable::query()
                ->where('order_id', $order->id)
                ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
                ->where('status', PartnerPayable::STATUS_OPEN)
                ->first();

            if ($payable) {
                $payableIds[] = (int) $payable->id;
            }
        }

        if ($payableIds === []) {
            $this->command?->warn('AccountsFinanceTestSeeder: no open kitchen payables for settlement batch.');

            return;
        }

        try {
            KitchenMoneyService::createSettlementBatch(
                (int) $kitchen->id,
                'Demo week remittance',
                $payableIds,
                PayoutChannel::CASH,
                [],
                (int) $actor->id,
                'Pending remittance packet for accounts approve UI (AccountsFinanceTestSeeder)'
            );
        } catch (\Throwable $e) {
            $this->command?->warn('AccountsFinanceTestSeeder: could not create settlement batch — '.$e->getMessage());
        }
    }
}
