<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\CashHandover;
use App\Models\CashHandoverOrder;
use App\Models\CustomRun;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\RiderWithdrawalRequest;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoOperatingCosts;
use App\Support\MiddoSettings;
use App\Support\RiderAccountLedger;
use App\Support\StaffAlerts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Demo fixtures for rider portal features (R0–R6 + R2b).
 *
 *   php artisan db:seed --class=DeliveryFeaturesTestSeeder
 *
 * Login: delivery@middo.com / 01310123454 / 12345678
 * Ops: operations@middo.com / 01310123455 / 12345678
 */
class DeliveryFeaturesTestSeeder extends Seeder
{
    public function run(): void
    {
        $rider = User::query()->where('email', 'delivery@middo.com')->orWhere('mobile', '01310123454')->first();
        $rider2 = User::query()->where('email', 'delivery2@middo.com')->orWhere('mobile', '01310123460')->first();
        $kitchen = User::query()->where('email', 'kitchen@middo.com')->orWhere('mobile', '01310123453')->first();
        $corporate = User::query()->where('email', 'corporate@middo.com')->orWhere('mobile', '01310123452')->first();
        $admin = User::query()->where('email', 'admin@middo.com')->orWhere('mobile', '01310123451')->first();
        $ops = User::query()->where('email', 'operations@middo.com')->orWhere('mobile', '01310123455')->first();
        $menus = MenuItem::query()->orderBy('id')->get();

        if (! $rider || ! $kitchen || ! $corporate || $menus->isEmpty()) {
            $this->command?->warn('DeliveryFeaturesTestSeeder skipped: need delivery, kitchen, corporate, and menus.');

            return;
        }

        MiddoSettings::updateMealAndKitchenDefaults([
            'delivery_commissions' => [
                DeliveryRunType::CORPORATE_TO_KITCHEN => 15,
                DeliveryRunType::KITCHEN_TO_OPS => 20,
                DeliveryRunType::OPS_TO_KITCHEN => 25,
                DeliveryRunType::CUSTOM => 40,
            ],
        ]);

        $this->seedMenuCommissions($menus);
        $this->seedRiderAreas($rider, $rider2, $kitchen);
        $this->seedWalletAndDue($rider, $kitchen, $corporate, $menus->first(), $admin);
        $this->seedOpsBoxCommission($rider);
        $this->seedLunchDispatchAlert($rider, $kitchen, $corporate, $menus->first());
        $this->seedCustomRuns($rider, $rider2, $ops ?? $admin);

        $this->command?->info('DeliveryFeaturesTestSeeder: areas, commissions, Due, wallet, withdrawal, alerts, custom runs ready.');
        $this->command?->info('  Rider: delivery@middo.com / 01310123454 / 12345678');
        $this->command?->info('  Ops:   operations@middo.com / 01310123455 / 12345678');
    }

    protected function seedMenuCommissions($menus): void
    {
        foreach ($menus as $i => $menu) {
            if ((int) ($menu->delivery_commission ?? 0) > 0) {
                continue;
            }
            $menu->update(['delivery_commission' => 30 + ($i * 5)]);
        }
    }

    protected function seedRiderAreas(User $rider, ?User $rider2, User $kitchen): void
    {
        if (! Schema::hasTable('area_user')) {
            return;
        }

        $areaIds = Area::query()->orderBy('id')->pluck('id')->take(2)->all();
        if ($areaIds === []) {
            return;
        }

        $rider->areas()->sync($areaIds);
        $rider->update(['area_id' => $areaIds[0]]);

        if ($rider2) {
            $rider2->areas()->sync([$areaIds[0]]);
            $rider2->update(['area_id' => $areaIds[0]]);
        }

        if ($kitchen->area_id === null) {
            $kitchen->update(['area_id' => $areaIds[0]]);
        }
    }

    protected function seedWalletAndDue(User $rider, User $kitchen, User $corporate, MenuItem $menu, ?User $admin): void
    {
        if (! Schema::hasTable('rider_account_ledger')) {
            return;
        }

        // Prepaid-style wallet Middo owes (no Due).
        if (RiderAccountLedger::balance($rider->id) < 1) {
            RiderAccountLedger::credit(
                $rider->id,
                80,
                'commission_accrued',
                null,
                null,
                'Demo prepaid lunch commission (DeliveryFeaturesTestSeeder)',
                $admin?->id ?? $rider->id
            );
        }

        RiderWithdrawalRequest::query()->updateOrCreate(
            [
                'rider_user_id' => $rider->id,
                'status' => RiderWithdrawalRequest::STATUS_PENDING,
            ],
            [
                'amount' => 40,
                'notes' => 'Demo payment request',
            ]
        );

        // Separate COD Due float sample (handover-ready).
        $today = now('Asia/Dhaka')->toDateString();
        $dueOrder = Order::query()->firstOrCreate(
            ['address' => 'DFEAT-DUE-DEMO'],
            [
                'user_id' => $corporate->id,
                'menu_item_id' => $menu->id,
                'quantity' => 1,
                'delivery_date' => $today,
                'delivery_time' => '1:00 PM',
                'total_amount' => (int) $menu->price,
                'amount_paid' => (int) $menu->price,
                'cash_collected' => (int) $menu->price,
                'cash_due_to_middo' => max(0, (int) $menu->price - 40),
                'order_status' => 'delivered_and_paid',
                'payment_status' => 'paid',
                'delivery_rider_id' => $rider->id,
                'area_id' => $rider->area_id,
            ]
        );

        $group = OrderGroup::query()->firstOrCreate(
            ['name' => 'GRP-DFEAT-DUE'],
            [
                'menu_id' => $menu->id,
                'delivery_date' => $today,
                'kitchen_id' => $kitchen->id,
                'area_id' => $rider->area_id,
            ]
        );
        $group->orders()->syncWithoutDetaching([$dueOrder->id]);

        $dueAmount = (int) ($dueOrder->cash_due_to_middo ?? max(0, (int) $dueOrder->cash_collected - 40));
        if ($dueAmount > 0 && (int) $rider->balance < $dueAmount) {
            $rider->update(['balance' => $dueAmount]);
        }

        if ($dueAmount > 0 && ! $dueOrder->cashHandoverOrder()->exists()) {
            $handover = CashHandover::query()->create([
                'rider_id' => $rider->id,
                'amount' => $dueAmount,
                'target' => CashHandover::TARGET_KITCHEN,
                'status' => 'pending',
                'notes' => 'Demo Due handover',
            ]);
            CashHandoverOrder::query()->create([
                'cash_handover_id' => $handover->id,
                'order_id' => $dueOrder->id,
                'amount' => $dueAmount,
            ]);
        }
    }

    protected function seedOpsBoxCommission(User $rider): void
    {
        if (! Schema::hasTable('middo_operating_costs')) {
            return;
        }

        $box = MiddoBox::query()->firstOrCreate(
            ['qr_code_id' => 'MB-DFEAT-OPS'],
            [
                'box_model_type' => 'standard_insulated',
                'asset_status' => 'active',
                'held_by_user_id' => $rider->id,
                'kitchen_id' => null,
                'total_uses_count' => 1,
            ]
        );

        MiddoOperatingCosts::bookRiderCommission(
            $rider,
            DeliveryRunType::OPS_TO_KITCHEN,
            25,
            MiddoBox::class,
            $box->id,
            'Demo ops→kitchen commission',
            $rider->id
        );
    }

    protected function seedLunchDispatchAlert(User $rider, User $kitchen, User $corporate, MenuItem $menu): void
    {
        if (! Schema::hasTable('staff_alerts')) {
            return;
        }

        $today = now('Asia/Dhaka')->toDateString();
        $order = Order::query()->firstOrCreate(
            ['address' => 'DFEAT-PACKED-DEMO'],
            [
                'user_id' => $corporate->id,
                'menu_item_id' => $menu->id,
                'quantity' => 1,
                'delivery_date' => $today,
                'delivery_time' => '12:30 PM',
                'total_amount' => (int) $menu->price,
                'order_status' => 'packed',
                'payment_status' => 'pending',
                'dispatched_at' => now(),
                'area_id' => $rider->area_id,
                'delivery_rider_id' => $rider->id,
                'original_delivery_rider_id' => $rider->id,
            ]
        );

        if (! $order->delivery_rider_id) {
            $order->update([
                'delivery_rider_id' => $rider->id,
                'original_delivery_rider_id' => $order->original_delivery_rider_id ?: $rider->id,
            ]);
        }

        $group = OrderGroup::query()->firstOrCreate(
            ['name' => 'GRP-DFEAT-PACKED'],
            [
                'menu_id' => $menu->id,
                'delivery_date' => $today,
                'kitchen_id' => $kitchen->id,
                'area_id' => $rider->area_id,
            ]
        );
        $group->orders()->syncWithoutDetaching([$order->id]);

        StaffAlerts::notifyRiderLunchAssigned($order->fresh(['menuItem', 'orderGroup', 'deliveryRider']));
    }

    protected function seedCustomRuns(User $rider, ?User $rider2, ?User $creator): void
    {
        if (! Schema::hasTable('custom_runs')) {
            return;
        }

        $areaId = $rider->area_id;
        $createdBy = $creator?->id ?? $rider->id;

        $openPool = CustomRun::query()->firstOrCreate(
            [
                'from_label' => 'Middo Warehouse',
                'to_label' => 'Gulshan Kitchen',
                'status' => CustomRun::STATUS_PENDING,
            ],
            [
                'area_id' => $areaId,
                'rider_user_id' => $rider->id,
                'commission_amount' => 40,
                'notes' => 'Demo assigned custom run (start from rider Custom runs)',
                'created_by' => $createdBy,
            ]
        );
        if (! $openPool->rider_user_id) {
            $openPool->update(['rider_user_id' => $rider->id]);
        }
        StaffAlerts::notifyRidersCustomRun($openPool->fresh(['rider', 'area']));

        CustomRun::query()->firstOrCreate(
            [
                'from_label' => 'Banani Office',
                'to_label' => 'Baridhara Drop',
                'status' => CustomRun::STATUS_PENDING,
                'rider_user_id' => $rider->id,
            ],
            [
                'area_id' => $areaId,
                'commission_amount' => 55,
                'notes' => 'Demo assigned custom run for Rahim',
                'created_by' => $createdBy,
            ]
        );

        $started = CustomRun::query()->firstOrCreate(
            [
                'from_label' => 'Ops Hub',
                'to_label' => 'Corporate HQ',
                'status' => CustomRun::STATUS_STARTED,
                'rider_user_id' => $rider->id,
            ],
            [
                'area_id' => $areaId,
                'commission_amount' => 35,
                'notes' => 'Demo in-progress custom run',
                'created_by' => $createdBy,
                'started_at' => now()->subHour(),
            ]
        );

        MiddoOperatingCosts::bookRiderCommission(
            $rider,
            DeliveryRunType::CUSTOM,
            (int) $started->commission_amount,
            CustomRun::class,
            (int) $started->id,
            'Demo custom run #'.$started->id.': '.$started->label(),
            $createdBy
        );

        if ($rider2) {
            CustomRun::query()->firstOrCreate(
                [
                    'from_label' => 'Mirpur Store',
                    'to_label' => 'Uttara Kitchen',
                    'status' => CustomRun::STATUS_PENDING,
                ],
                [
                    'area_id' => $rider2->area_id,
                    'rider_user_id' => $rider2->id,
                    'commission_amount' => 30,
                    'notes' => 'Demo assigned run for second rider',
                    'created_by' => $createdBy,
                ]
            );
        }

        CustomRun::query()->firstOrCreate(
            [
                'from_label' => 'Completed Sample',
                'to_label' => 'Archive',
                'status' => CustomRun::STATUS_COMPLETED,
            ],
            [
                'area_id' => $areaId,
                'rider_user_id' => $rider->id,
                'commission_amount' => 20,
                'notes' => 'Demo completed custom run',
                'created_by' => $createdBy,
                'started_at' => now()->subDay(),
                'completed_at' => now()->subHours(20),
            ]
        );
    }
}
