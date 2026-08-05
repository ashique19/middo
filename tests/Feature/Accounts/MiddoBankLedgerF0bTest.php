<?php

namespace Tests\Feature\Accounts;

use App\Contracts\PaymentGateway;
use App\Livewire\Admin\BankAccountsPage;
use App\Livewire\Shared\MiddoBankLedgerPage;
use App\Models\MiddoBankAccount;
use App\Models\MiddoBankLedgerEntry;
use App\Models\Role;
use App\Models\User;
use App\Support\CorporateWalletTopUp;
use App\Support\EpsSubGateway;
use App\Support\MiddoBankLedger;
use App\Support\MiddoSettings;
use App\Support\Payments\EpsPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MiddoBankLedgerF0bTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.driver' => 'eps',
            'payments.eps.sandbox' => true,
            'payments.eps.merchant_id' => '29e86e70-0ac6-45eb-ba04-9fcb0aaed12a',
            'payments.eps.store_id' => 'd44e705f-9e3a-41de-98b1-1674631637da',
            'payments.eps.username' => 'Epsdemo@gmail.com',
            'payments.eps.password' => 'Epsdemo258@',
            'payments.eps.hash_key' => 'FHZxyzeps56789gfhg678ygu876o=',
        ]);

        $this->app->forgetInstance(PaymentGateway::class);
        $this->app->singleton(PaymentGateway::class, fn () => new EpsPaymentGateway);
    }

    #[Test]
    public function admin_can_create_bank_account_and_eps_credits_net(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $corporateRole = Role::create(['name' => 'corporate']);
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Bank',
            'mobile' => '01890000001',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);
        $corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'Pay',
            'mobile' => '01890000002',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
            'balance' => 0,
        ]);

        MiddoSettings::updateMealAndKitchenDefaults([
            'eps_fee_rates' => [
                'bank' => 1.5,
                'bkash' => 1.8,
                'nagad' => 1.8,
                'rocket' => 1.8,
                'card' => 2.5,
                'other' => 1.5,
            ],
        ]);

        Livewire::actingAs($admin)
            ->test(BankAccountsPage::class)
            ->call('openCreate')
            ->set('name', 'Ops float')
            ->set('bank_name', 'BRAC')
            ->set('account_number', '123456')
            ->set('is_default', true)
            ->call('save')
            ->assertSet('errorMessage', '');

        $account = MiddoBankAccount::query()->first();
        $this->assertNotNull($account);
        $this->assertTrue($account->is_default);

        Http::fake([
            'https://sandboxpgapi.eps.com.bd/v1/Auth/GetToken' => Http::response(['token' => 't'], 200),
            'https://sandboxpgapi.eps.com.bd/v1/EPSEngine/InitializeEPS' => Http::response([
                'TransactionId' => 'eps-bank-1',
                'RedirectURL' => 'https://pg.eps.com.bd/PG?data=eps-bank-1',
                'ErrorMessage' => '',
            ], 200),
            'https://sandboxpgapi.eps.com.bd/v1/EPSEngine/CheckMerchantTransactionStatus*' => Http::response([
                'Status' => 'Success',
                'TotalAmount' => '1000.00',
                'FinancialEntityName' => 'bKash',
                'ErrorMessage' => '',
            ], 200),
        ]);

        $checkout = app(PaymentGateway::class)->createCheckout($corporate->id, 1000, [
            'purpose' => CorporateWalletTopUp::PURPOSE,
            'user_id' => $corporate->id,
        ]);

        $this->get(route('payments.eps.success', [
            'token' => $checkout['token'],
            'merchantTransactionId' => $checkout['merchant_transaction_id'],
        ]))->assertRedirect();

        // 1.8% of 1000 = 18 fee → net 982
        $entry = MiddoBankLedgerEntry::query()->where('gateway_token', $checkout['token'])->first();
        $this->assertNotNull($entry);
        $this->assertSame(MiddoBankLedgerEntry::TYPE_EPS_IN_NET, $entry->entry_type);
        $this->assertSame(EpsSubGateway::BKASH, $entry->sub_gateway);
        $this->assertSame(1000, (int) $entry->gross_amount);
        $this->assertSame(18, (int) $entry->fee_amount);
        $this->assertSame(982, (int) $entry->amount);
        $this->assertSame(982, MiddoBankLedger::balance((int) $account->id));

        // Idempotent on replay
        $this->get(route('payments.eps.success', [
            'token' => $checkout['token'],
            'merchantTransactionId' => $checkout['merchant_transaction_id'],
        ]));
        $this->assertSame(1, MiddoBankLedgerEntry::query()->where('gateway_token', $checkout['token'])->count());

        Livewire::actingAs($admin)
            ->test(MiddoBankLedgerPage::class)
            ->assertOk()
            ->assertSee('Ops float', false);
    }

    #[Test]
    public function eps_sub_gateway_parser_maps_common_labels(): void
    {
        $this->assertSame(EpsSubGateway::BKASH, EpsSubGateway::fromEpsRaw(['FinancialEntityName' => 'bKash Wallet']));
        $this->assertSame(EpsSubGateway::NAGAD, EpsSubGateway::fromEpsRaw(['PaymentMethod' => 'Nagad']));
        $this->assertSame(EpsSubGateway::BANK, EpsSubGateway::fromEpsRaw(['PaymentType' => 'Bank Transfer']));
        $this->assertSame(EpsSubGateway::OTHER, EpsSubGateway::fromEpsRaw([]));
    }
}
