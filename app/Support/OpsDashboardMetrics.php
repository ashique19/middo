<?php

namespace App\Support;

use App\Models\CashHandover;
use App\Models\MealItem;
use App\Models\MealPackage;
use App\Models\MenuItem;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\PackageSubscription;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OpsDashboardMetrics
{
    /**
     * @return array<string, mixed>
     */
    public static function forRole(string $role): array
    {
        $tz = OrderCutoff::timezone();
        $todayDate = now($tz)->toDateString();
        $tomorrowDate = now($tz)->addDay()->toDateString();

        $todayStats = self::dayStats($todayDate);
        $tomorrowStats = self::dayStats($tomorrowDate);

        $activePipeline = Order::query()->future()->active()->count();
        $activeQty = (int) Order::query()->future()->active()->sum('quantity');

        $ungrouped = Order::query()
            ->future()
            ->active()
            ->whereDoesntHave('orderGroupOrder')
            ->count();

        $awaitingSchedule = PackageSubscription::query()
            ->active()
            ->where('schedule_status', PackageSubscription::SCHEDULE_AWAITING)
            ->count();

        $activePackages = PackageSubscription::query()->active()->count();
        $prepaidRevenue = (int) PackageSubscription::query()->sum('amount_paid');

        $pendingHandovers = Schema::hasTable('cash_handovers')
            ? CashHandover::query()->where('status', 'pending')->count()
            : 0;

        $pendingHandoverAmount = Schema::hasTable('cash_handovers')
            ? (int) CashHandover::query()->where('status', 'pending')->sum('amount')
            : 0;

        $openComplaints = 0;
        if (Schema::hasTable('order_complaints')) {
            $q = OrderComplaint::query();
            if (Schema::hasColumn('order_complaints', 'parent_id')) {
                $q->whereNull('parent_id');
            }
            if (Schema::hasColumn('order_complaints', 'status')) {
                $q->whereNotIn('status', ['resolved', 'closed']);
            }
            $openComplaints = $q->count();
        }

        $kitchenRoleId = Role::query()->where('name', 'kitchen')->value('id');
        $deliveryRoleId = Role::query()->where('name', 'delivery')->value('id');
        $corporateRoleId = Role::query()->where('name', 'corporate')->value('id');

        $activeKitchens = $kitchenRoleId
            ? User::query()->where('role_id', $kitchenRoleId)->where('status', 'active')->count()
            : 0;
        $pendingKitchens = $kitchenRoleId
            ? User::query()->where('role_id', $kitchenRoleId)->where('status', 'pending')->count()
            : 0;
        $activeRiders = $deliveryRoleId
            ? User::query()->where('role_id', $deliveryRoleId)->where('status', 'active')->count()
            : 0;
        $corporates = $corporateRoleId
            ? User::query()->where('role_id', $corporateRoleId)->where('status', 'active')->count()
            : 0;

        $riderCashFloat = $deliveryRoleId
            ? (int) User::query()->where('role_id', $deliveryRoleId)->sum('balance')
            : 0;

        $middoCash = Schema::hasTable('middo_cash_ledger')
            ? MiddoCashLedger::balance()
            : null;

        $openKitchenPayables = 0;
        $openDeliveryPayables = 0;
        $hasPayables = Schema::hasTable('partner_payables');
        if ($hasPayables) {
            $openKitchenPayables = (int) DB::table('partner_payables')
                ->where('status', 'open')
                ->where('beneficiary_role', 'kitchen')
                ->sum('amount');
            $openDeliveryPayables = (int) DB::table('partner_payables')
                ->where('status', 'open')
                ->where('beneficiary_role', 'delivery')
                ->sum('amount');
        }

        $upcoming = self::upcomingDays(7, $tz);

        $statusBreakdown = Order::query()
            ->whereDate('delivery_date', $todayDate)
            ->where('order_status', '!=', 'cancelled')
            ->select('order_status', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(quantity) as qty'))
            ->groupBy('order_status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (string) $row->order_status => [
                    'orders' => (int) $row->order_count,
                    'qty' => (int) $row->qty,
                ],
            ])
            ->all();

        $attention = array_values(array_filter([
            $ungrouped > 0 ? [
                'label' => 'Ungrouped active orders',
                'value' => $ungrouped,
                'tone' => 'amber',
                'route' => $role.'.orders.active',
                'hint' => 'Need kitchen grouping before dispatch',
            ] : null,
            $awaitingSchedule > 0 ? [
                'label' => 'Packages awaiting schedule',
                'value' => $awaitingSchedule,
                'tone' => 'sky',
                'route' => $role.'.subscriptions.index',
                'hint' => 'Corporate prepaid — assign delivery dates',
            ] : null,
            $pendingHandovers > 0 ? [
                'label' => 'Pending cash handovers',
                'value' => $pendingHandovers,
                'amount' => $pendingHandoverAmount,
                'tone' => 'emerald',
                'route' => null,
                'hint' => 'Rider cash waiting kitchen accept',
            ] : null,
            $role === 'admin' && $pendingKitchens > 0 ? [
                'label' => 'Kitchens pending onboarding',
                'value' => $pendingKitchens,
                'tone' => 'rose',
                'route' => 'admin.kitchens.onboarding',
                'hint' => 'Activate before they can cook',
            ] : null,
            $openComplaints > 0 ? [
                'label' => 'Open complaints',
                'value' => $openComplaints,
                'tone' => 'rose',
                'route' => null,
                'hint' => 'Customer issues logged on orders',
            ] : null,
        ]));

        $quickLinks = self::quickLinks($role);

        $catalog = [
            'menus' => MenuItem::query()->count(),
            'meal_items' => MealItem::query()->count(),
            'packages_published' => MealPackage::query()->published()->count(),
            'middo_boxes' => MiddoBox::query()->count(),
            'kitchens' => $activeKitchens,
            'riders' => $activeRiders,
            'corporates' => $corporates,
        ];

        $usersByRole = [];
        if ($role === 'admin') {
            $usersByRole = Role::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Role $r) => [
                    'name' => $r->name,
                    'count' => User::query()
                        ->where('role_id', $r->id)
                        ->where('status', 'active')
                        ->count(),
                ])
                ->all();
        }

        return [
            'role' => $role,
            'today_date' => $todayDate,
            'tomorrow_date' => $tomorrowDate,
            'today_label' => Carbon::parse($todayDate, $tz)->format('D, M j'),
            'tomorrow_label' => Carbon::parse($tomorrowDate, $tz)->format('D, M j'),
            'today' => array_merge(['date' => $todayDate], $todayStats),
            'tomorrow' => array_merge(['date' => $tomorrowDate], $tomorrowStats),
            'pipeline' => [
                'orders' => $activePipeline,
                'qty' => $activeQty,
                'ungrouped' => $ungrouped,
            ],
            'status_breakdown' => $statusBreakdown,
            'packages' => [
                'active' => $activePackages,
                'awaiting_schedule' => $awaitingSchedule,
                'prepaid_revenue' => $prepaidRevenue,
            ],
            'money' => [
                'middo_cash' => $middoCash,
                'rider_cash_float' => $riderCashFloat,
                'pending_handovers' => $pendingHandovers,
                'pending_handover_amount' => $pendingHandoverAmount,
                'open_kitchen_payables' => $openKitchenPayables,
                'open_delivery_payables' => $openDeliveryPayables,
                'has_accounts' => $hasPayables,
                'has_cash_ledger' => Schema::hasTable('middo_cash_ledger'),
            ],
            'attention' => $attention,
            'upcoming' => $upcoming,
            'catalog' => $catalog,
            'users_by_role' => $usersByRole,
            'quick_links' => $quickLinks,
            'pending_kitchens' => $pendingKitchens,
        ];
    }

    /**
     * @return array{orders:int,qty:int,ungrouped:int,groups:int,revenue:int}
     */
    protected static function dayStats(string $date): array
    {
        $base = Order::query()
            ->whereDate('delivery_date', $date)
            ->where('order_status', '!=', 'cancelled');

        $orders = (clone $base)->count();
        $qty = (int) (clone $base)->sum('quantity');
        $revenue = (int) (clone $base)->sum('total_amount');
        $ungrouped = (clone $base)
            ->whereIn('order_status', Order::ACTIVE_STATUSES)
            ->whereDoesntHave('orderGroupOrder')
            ->count();
        $groups = (int) DB::table('order_groups')
            ->whereDate('delivery_date', $date)
            ->count();

        return compact('orders', 'qty', 'ungrouped', 'groups', 'revenue');
    }

    /**
     * @return list<array<string,mixed>>
     */
    protected static function upcomingDays(int $days, string $tz): array
    {
        $rows = [];
        for ($i = 0; $i < $days; $i++) {
            $date = now($tz)->addDays($i)->toDateString();
            $stats = self::dayStats($date);
            if ($stats['orders'] === 0 && $i > 1) {
                // Still include today/tomorrow even if empty; skip farther empty days.
                continue;
            }
            $rows[] = array_merge([
                'date' => $date,
                'label' => Carbon::parse($date, $tz)->format('D, M j'),
                'is_today' => $i === 0,
                'is_tomorrow' => $i === 1,
            ], $stats);
        }

        return $rows;
    }

    /**
     * @return list<array{label:string,route:string,hint:string}>
     */
    protected static function quickLinks(string $role): array
    {
        $links = [
            ['label' => 'Active orders', 'route' => $role.'.orders.active', 'hint' => 'Group & dispatch'],
            ['label' => 'Corporates', 'route' => $role.'.corporates.index', 'hint' => 'Accounts & history'],
            ['label' => 'Kitchens', 'route' => $role === 'admin' ? 'admin.kitchens.active' : 'operation.kitchens.index', 'hint' => 'Kitchen roster'],
            ['label' => 'Packages', 'route' => $role.'.packages.index', 'hint' => 'Rate plans'],
            ['label' => 'Subscriptions', 'route' => $role.'.subscriptions.index', 'hint' => 'Schedule & manage'],
            ['label' => 'Package demand', 'route' => $role.'.packages.demand', 'hint' => 'Tomorrow’s volume'],
            ['label' => 'Package insights', 'route' => $role.'.packages.insights', 'hint' => 'Prepaid & refunds'],
            ['label' => 'Middo cash', 'route' => $role.'.middo-cash', 'hint' => 'Cash ledger'],
        ];

        if ($role === 'operation') {
            $links[] = ['label' => 'Middo boxes', 'route' => 'operation.middo-boxes.index', 'hint' => 'Box inventory'];
            $links[] = ['label' => 'Menu', 'route' => 'operation.menu.index', 'hint' => 'Catalog'];
        }

        if ($role === 'admin') {
            $links[] = ['label' => 'Kitchen onboarding', 'route' => 'admin.kitchens.onboarding', 'hint' => 'Pending kitchens'];
            $links[] = ['label' => 'Users', 'route' => 'admin.users.index', 'hint' => 'Staff & roles'];
            $links[] = ['label' => 'Navs & roles', 'route' => 'admin.navrole.index', 'hint' => 'System nav'];
        }

        if (\Illuminate\Support\Facades\Route::has($role.'.accounts.index')) {
            array_splice($links, 7, 0, [[
                'label' => 'Accounts',
                'route' => $role.'.accounts.index',
                'hint' => 'Money flow & payables',
            ]]);
        }

        return $links;
    }
}
