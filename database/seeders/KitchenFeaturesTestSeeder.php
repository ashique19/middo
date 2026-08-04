<?php

namespace Database\Seeders;

use App\Models\KitchenHour;
use App\Models\KitchenMiddoTransfer;
use App\Models\KitchenWithdrawalRequest;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\OrderGroup;
use App\Models\OrderGroupEvent;
use App\Models\StaffAlert;
use App\Models\User;
use App\Support\KitchenAccountLedger;
use App\Support\KitchenPermissions;
use App\Support\KitchenTier;
use App\Support\MiddoSettings;
use App\Support\StaffAlerts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Demo fixtures for kitchen features shipped in K0–K6 + app shell.
 *
 * Run after the base DatabaseSeeder (or alone if users/menus already exist):
 *   php artisan db:seed --class=KitchenFeaturesTestSeeder
 *
 * Login: kitchen@middo.com / 01310123453 / 12345678
 */
class KitchenFeaturesTestSeeder extends Seeder
{
    public function run(): void
    {
        $gulshan = User::query()->where('email', 'kitchen@middo.com')->orWhere('mobile', '01310123453')->first();
        $banani = User::query()->where('email', 'kitchen2@middo.com')->orWhere('mobile', '01310123456')->first();
        $admin = User::query()->where('email', 'admin@middo.com')->orWhere('mobile', '01310123451')->first();
        $ops = User::query()->where('email', 'operations@middo.com')->orWhere('mobile', '01310123455')->first();
        $corporate = User::query()->where('email', 'corporate@middo.com')->orWhere('mobile', '01310123452')->first();
        $menus = MenuItem::query()->orderBy('display_order')->get();

        if (! $gulshan || ! $corporate || $menus->isEmpty()) {
            $this->command?->warn('KitchenFeaturesTestSeeder skipped: need kitchen@middo.com, corporate, and menus.');

            return;
        }

        KitchenPermissions::syncKitchenRole();

        MiddoSettings::updateMealAndKitchenDefaults([
            'accept_window_minutes' => 120,
            'accept_window_warn_minutes' => 15,
            'auto_group_quantity' => 10,
            'tier_defaults' => [
                KitchenTier::SILVER => 1,
                KitchenTier::GOLD => 2,
                KitchenTier::PLATINUM => 3,
            ],
        ]);

        $this->seedKitchenProfiles($gulshan, $banani);
        $this->seedHours($gulshan);
        $this->seedLifecycleGroups($gulshan, $banani, $corporate, $menus, $admin);
        $this->seedMoney($gulshan, $admin);
        $this->seedDamagedBox($gulshan);
        $this->seedAlerts($gulshan, $admin, $ops);

        $this->command?->info('KitchenFeaturesTestSeeder: tiers, hours, groups, money, damage, alerts, complaints ready for Gulshan kitchen.');
    }

    protected function seedKitchenProfiles(User $gulshan, ?User $banani): void
    {
        $gulshan->update([
            'kitchen_tier' => KitchenTier::GOLD,
            'allowed_open_groups' => 2,
            'status' => 'active',
        ]);

        if ($banani) {
            $banani->update([
                'kitchen_tier' => KitchenTier::SILVER,
                'allowed_open_groups' => 1,
                'status' => 'active',
            ]);
        }

        User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))
            ->where('status', 'active')
            ->whereNull('kitchen_tier')
            ->update([
                'kitchen_tier' => KitchenTier::SILVER,
                'allowed_open_groups' => 1,
            ]);
    }

    protected function seedHours(User $kitchen): void
    {
        if (! Schema::hasTable('kitchen_hours')) {
            return;
        }

        foreach (KitchenHour::DAYS as $day => $_label) {
            KitchenHour::query()->updateOrCreate(
                [
                    'user_id' => $kitchen->id,
                    'day_of_week' => $day,
                ],
                [
                    'is_closed' => $day === 5, // Friday closed sample
                    'opens_at' => $day === 5 ? null : '10:00',
                    'closes_at' => $day === 5 ? null : '22:00',
                ]
            );
        }
    }

    protected function seedLifecycleGroups(
        User $gulshan,
        ?User $banani,
        User $corporate,
        $menus,
        ?User $admin
    ): void {
        $menu = $menus[0];
        $menuB = $menus[1] ?? $menus[0];
        $today = now('Asia/Dhaka');
        $deliverySoon = $today->copy()->addMinutes(12);
        $deliveryLater = $today->copy()->addHours(2);

        // Open pool group — accept window open / closing soon (SLA demo)
        $openGroup = OrderGroup::query()->firstOrCreate(
            ['name' => 'GRP-KFEAT-OPEN'],
            [
                'menu_id' => $menu->id,
                'delivery_date' => $deliverySoon->toDateString(),
                'kitchen_id' => null,
                'created_by' => $admin?->id,
            ]
        );
        $openGroup->update([
            'menu_id' => $menu->id,
            'delivery_date' => $deliverySoon->toDateString(),
            'kitchen_id' => null,
        ]);
        if ($openGroup->orders()->count() === 0) {
            $order = $this->makeOrder($corporate, $menu, $deliverySoon, 3, 'pending');
            $openGroup->orders()->attach($order->id);
        }

        // Assigned processing group for Active Orders + complaint scope
        $activeGroup = OrderGroup::query()->firstOrCreate(
            ['name' => 'GRP-KFEAT-ACTIVE'],
            [
                'menu_id' => $menuB->id,
                'delivery_date' => $deliveryLater->toDateString(),
                'kitchen_id' => $gulshan->id,
                'created_by' => $admin?->id,
            ]
        );
        $activeGroup->update([
            'menu_id' => $menuB->id,
            'delivery_date' => $deliveryLater->toDateString(),
            'kitchen_id' => $gulshan->id,
        ]);

        if ($activeGroup->orders()->count() === 0) {
            $processing = $this->makeOrder($corporate, $menuB, $deliveryLater, 2, 'processing');
            $ready = $this->makeOrder($corporate, $menuB, $deliveryLater, 1, 'ready');
            $activeGroup->orders()->attach([$processing->id, $ready->id]);

            OrderGroupEvent::query()->firstOrCreate(
                [
                    'order_group_id' => $activeGroup->id,
                    'type' => OrderGroupEvent::TYPE_ACCEPT,
                    'kitchen_id' => $gulshan->id,
                ],
                [
                    'created_by' => $gulshan->id,
                ]
            );

            OrderComplaint::query()->firstOrCreate(
                [
                    'order_id' => $processing->id,
                    'parent_id' => null,
                    'message' => 'Spice level was higher than expected on yesterday’s similar menu — please go mild today.',
                ],
                [
                    'is_reply' => false,
                    'category' => 'food_quality',
                    'created_by' => $corporate->id,
                    'updated_by' => $corporate->id,
                ]
            );
        }

        // Sample shortage history on another name (ops alert demo)
        if ($banani) {
            OrderGroupEvent::query()->firstOrCreate(
                [
                    'order_group_id' => $openGroup->id,
                    'type' => OrderGroupEvent::TYPE_SHORTAGE,
                    'kitchen_id' => $banani->id,
                ],
                [
                    'reason' => 'Out of chicken for this menu',
                    'created_by' => $banani->id,
                ]
            );
        }
    }

    protected function seedMoney(User $kitchen, ?User $admin): void
    {
        if (! Schema::hasTable('kitchen_account_ledger')) {
            return;
        }

        if (KitchenAccountLedger::balance($kitchen->id) < 1) {
            KitchenAccountLedger::credit(
                $kitchen->id,
                1500,
                'share_accrued',
                null,
                null,
                'Demo kitchen share accrual (KitchenFeaturesTestSeeder)',
                $admin?->id
            );
            KitchenAccountLedger::credit(
                $kitchen->id,
                800,
                'share_accrued',
                null,
                null,
                'Second demo accrual',
                $admin?->id
            );
        }

        if (! KitchenWithdrawalRequest::query()->where('kitchen_user_id', $kitchen->id)->where('status', 'pending')->exists()) {
            KitchenWithdrawalRequest::create([
                'kitchen_user_id' => $kitchen->id,
                'amount' => 800,
                'status' => KitchenWithdrawalRequest::STATUS_PENDING,
                'notes' => 'Demo withdrawal for ops approval UI',
            ]);
        }

        if (! KitchenMiddoTransfer::query()->where('kitchen_user_id', $kitchen->id)->where('status', 'pending')->exists()) {
            KitchenMiddoTransfer::create([
                'kitchen_user_id' => $kitchen->id,
                'amount' => 500,
                'status' => KitchenMiddoTransfer::STATUS_PENDING,
                'reference_code' => 'BKASH-DEMO-001',
                'notes' => 'Demo cash sent to Middo — awaiting confirm',
                'proof_path' => null,
            ]);
        }
    }

    protected function seedDamagedBox(User $kitchen): void
    {
        if (! Schema::hasTable('middo_boxes')) {
            return;
        }

        $exists = MiddoBox::query()
            ->where('kitchen_id', $kitchen->id)
            ->where('asset_status', 'damaged')
            ->exists();

        if ($exists) {
            return;
        }

        $box = MiddoBox::create([
            'qr_code_id' => 'MB-DMG-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'box_model_type' => 'standard_insulated',
            'kitchen_id' => $kitchen->id,
            'held_by_user_id' => $kitchen->id,
            'asset_status' => 'damaged',
            'total_uses_count' => 1,
        ]);

        MiddoBoxLog::create([
            'middo_box_id' => $box->id,
            'custody_status' => 'assigned_at_kitchen',
            'log_action' => 'marked_damaged',
            'notes' => 'Demo damaged lid hinge (KitchenFeaturesTestSeeder)',
            'performed_by' => $kitchen->id,
        ]);
    }

    protected function seedAlerts(User $kitchen, ?User $admin, ?User $ops): void
    {
        if (! Schema::hasTable('staff_alerts')) {
            return;
        }

        StaffAlerts::notifyKitchenAssigned(
            OrderGroup::query()->where('name', 'GRP-KFEAT-ACTIVE')->first() ?? OrderGroup::query()->latest('id')->first(),
            $kitchen
        );

        $open = OrderGroup::query()->where('name', 'GRP-KFEAT-OPEN')->first();
        if ($open) {
            StaffAlerts::notifyKitchenAcceptWindowClosing($open, $kitchen);
        }

        if ($admin && $open) {
            StaffAlert::query()->firstOrCreate(
                [
                    'user_id' => $admin->id,
                    'dedupe_key' => 'seed:reassign:'.$open->id.':'.$admin->id,
                ],
                [
                    'type' => StaffAlert::TYPE_NEEDS_REASSIGNMENT,
                    'title' => 'Shortage: '.$open->name,
                    'body' => 'Demo shortage needs reassignment (KitchenFeaturesTestSeeder).',
                    'order_group_id' => $open->id,
                    'meta' => ['seeded' => true],
                ]
            );
        }

        if ($ops && $open) {
            StaffAlert::query()->firstOrCreate(
                [
                    'user_id' => $ops->id,
                    'dedupe_key' => 'seed:reassign:'.$open->id.':'.$ops->id,
                ],
                [
                    'type' => StaffAlert::TYPE_NEEDS_REASSIGNMENT,
                    'title' => 'Shortage: '.$open->name,
                    'body' => 'Demo shortage needs reassignment (KitchenFeaturesTestSeeder).',
                    'order_group_id' => $open->id,
                    'meta' => ['seeded' => true],
                ]
            );
        }
    }

    protected function makeOrder(User $corporate, MenuItem $menu, $deliveryAt, int $qty, string $status): Order
    {
        return Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => $qty,
            'delivery_date' => $deliveryAt->toDateString(),
            'delivery_time' => $deliveryAt->format('g:i A'),
            'total_amount' => (int) $menu->price * $qty,
            'address' => $corporate->address ?: 'Demo office',
            'order_status' => $status,
            'payment_status' => 'pending',
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);
    }
}
