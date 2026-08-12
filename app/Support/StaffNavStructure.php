<?php

namespace App\Support;

/**
 * Canonical desktop sidebar sections per staff role.
 * Parents are always-visible labels; children are the clickable links.
 *
 * @phpstan-type NavItem array{title: string, route_name: string, icon?: string|null}
 * @phpstan-type NavSection array{title: string, items: list<NavItem>}
 */
class StaffNavStructure
{
    /**
     * @return list<string>
     */
    public static function roleNames(): array
    {
        return [
            'admin',
            'operation',
            'accounts',
            'kitchen',
            'delivery',
            'ground_marketing',
            'corporate',
        ];
    }

    /**
     * @return list<NavSection>
     */
    public static function sectionsFor(string $roleName): array
    {
        return match ($roleName) {
            'admin' => self::admin(),
            'operation' => self::operation(),
            'accounts' => self::accounts(),
            'kitchen' => self::kitchen(),
            'delivery' => self::delivery(),
            'ground_marketing' => self::groundMarketing(),
            'corporate' => self::corporate(),
            default => [],
        };
    }

    /**
     * @return list<NavSection>
     */
    protected static function admin(): array
    {
        return [
            [
                'title' => 'Overview',
                'items' => [
                    ['title' => 'Dashboard', 'route_name' => 'admin.dashboard', 'icon' => '🏠'],
                    ['title' => 'Alerts', 'route_name' => 'admin.alerts.index', 'icon' => '🔔'],
                    ['title' => 'Ops day', 'route_name' => 'admin.ops-day.index', 'icon' => '📅'],
                ],
            ],
            [
                'title' => 'Catalog',
                'items' => [
                    ['title' => 'Menu items', 'route_name' => 'admin.menu.index', 'icon' => '🍽️'],
                    ['title' => 'Meal items', 'route_name' => 'admin.meal-items.index', 'icon' => '🥗'],
                    ['title' => 'Packages', 'route_name' => 'admin.packages.index', 'icon' => '🍱'],
                    ['title' => 'Subscriptions', 'route_name' => 'admin.subscriptions.index', 'icon' => '🔁'],
                    ['title' => 'Package demand', 'route_name' => 'admin.packages.demand', 'icon' => '📈'],
                    ['title' => 'Package insights', 'route_name' => 'admin.packages.insights', 'icon' => '🔎'],
                    ['title' => 'Coupons', 'route_name' => 'admin.coupons.index', 'icon' => '🏷️'],
                    ['title' => 'Charges', 'route_name' => 'admin.charges.index', 'icon' => '💸'],
                ],
            ],
            [
                'title' => 'Orders',
                'items' => [
                    ['title' => 'Active orders', 'route_name' => 'admin.orders.active', 'icon' => '📦'],
                    ['title' => 'Order History', 'route_name' => 'admin.orders.history', 'icon' => '🗂️'],
                    ['title' => 'Search Order', 'route_name' => 'admin.orders.search', 'icon' => '🔍'],
                ],
            ],
            [
                'title' => 'People',
                'items' => [
                    ['title' => 'Active Kitchens', 'route_name' => 'admin.kitchens.active', 'icon' => '👨‍🍳'],
                    ['title' => 'Onboarding', 'route_name' => 'admin.kitchens.onboarding', 'icon' => '📝'],
                    ['title' => 'Admins', 'route_name' => 'admin.users.admin', 'icon' => '🛡️'],
                    ['title' => 'Operations', 'route_name' => 'admin.users.operation', 'icon' => '🧭'],
                    ['title' => 'Accounts', 'route_name' => 'admin.users.accounts', 'icon' => '📒'],
                    ['title' => 'Kitchens', 'route_name' => 'admin.users.kitchen', 'icon' => '👨‍🍳'],
                    ['title' => 'Corporates', 'route_name' => 'admin.corporates.index', 'icon' => '🏢'],
                    ['title' => 'Delivery', 'route_name' => 'admin.users.delivery', 'icon' => '🛵'],
                    ['title' => 'Ground marketing', 'route_name' => 'admin.users.ground_marketing', 'icon' => '📣'],
                ],
            ],
            [
                'title' => 'Logistics',
                'items' => [
                    ['title' => 'Custom runs', 'route_name' => 'admin.custom-runs.index', 'icon' => '📍'],
                    ['title' => 'Rider ops', 'route_name' => 'admin.riders.index', 'icon' => '🛵'],
                    ['title' => 'Areas & cities', 'route_name' => 'admin.areas.index', 'icon' => '🗺️'],
                    ['title' => 'Coverage', 'route_name' => 'admin.coverage.index', 'icon' => '📍'],
                    ['title' => 'Dispatch SLA', 'route_name' => 'admin.sla.index', 'icon' => '⏱️'],
                ],
            ],
            [
                'title' => 'Money',
                'items' => [
                    ['title' => 'Kitchen money', 'route_name' => 'admin.kitchen-money.index', 'icon' => '💰'],
                    ['title' => 'Rider money', 'route_name' => 'admin.rider-money.index', 'icon' => '🛵'],
                    ['title' => 'Rider cash handovers', 'route_name' => 'admin.cash-handovers', 'icon' => '💵'],
                    ['title' => 'Payout banks', 'route_name' => 'admin.payout-banks.index', 'icon' => '🏛️'],
                    ['title' => 'Bank accounts', 'route_name' => 'admin.bank-accounts.index', 'icon' => '🏦'],
                    ['title' => 'Bank ledger', 'route_name' => 'admin.bank-ledger', 'icon' => '📒'],
                    ['title' => 'Period P&L', 'route_name' => 'admin.period-pnl', 'icon' => '📊'],
                ],
            ],
            [
                'title' => 'Support',
                'items' => [
                    ['title' => 'Complaints', 'route_name' => 'admin.complaints.index', 'icon' => '💬'],
                ],
            ],
            [
                'title' => 'System',
                'items' => [
                    ['title' => 'Settings', 'route_name' => 'admin.settings.index', 'icon' => '⚙️'],
                    ['title' => 'System Navs', 'route_name' => 'admin.navrole.index', 'icon' => '🧩'],
                ],
            ],
        ];
    }

    /**
     * @return list<NavSection>
     */
    protected static function operation(): array
    {
        return [
            [
                'title' => 'Overview',
                'items' => [
                    ['title' => 'Dashboard', 'route_name' => 'operation.dashboard', 'icon' => '🏠'],
                    ['title' => 'Alerts', 'route_name' => 'operation.alerts.index', 'icon' => '🔔'],
                    ['title' => 'Ops day', 'route_name' => 'operation.ops-day.index', 'icon' => '📅'],
                ],
            ],
            [
                'title' => 'Partners',
                'items' => [
                    ['title' => 'Corporates', 'route_name' => 'operation.corporates.index', 'icon' => '🏢'],
                    ['title' => 'Kitchens', 'route_name' => 'operation.kitchens.index', 'icon' => '👨‍🍳'],
                ],
            ],
            [
                'title' => 'Catalog',
                'items' => [
                    ['title' => 'Menu items', 'route_name' => 'operation.menu.index', 'icon' => '🍽️'],
                    ['title' => 'Meal items', 'route_name' => 'operation.meal-items.index', 'icon' => '🥗'],
                ],
            ],
            [
                'title' => 'Orders',
                'items' => [
                    ['title' => 'Active orders', 'route_name' => 'operation.orders.active', 'icon' => '📦'],
                    ['title' => 'Order History', 'route_name' => 'operation.orders.history', 'icon' => '🗂️'],
                    ['title' => 'Search Order', 'route_name' => 'operation.orders.search', 'icon' => '🔍'],
                ],
            ],
            [
                'title' => 'Packages',
                'items' => [
                    ['title' => 'Packages', 'route_name' => 'operation.packages.index', 'icon' => '🍱'],
                    ['title' => 'Subscriptions', 'route_name' => 'operation.subscriptions.index', 'icon' => '🔁'],
                    ['title' => 'Package demand', 'route_name' => 'operation.packages.demand', 'icon' => '📈'],
                    ['title' => 'Package insights', 'route_name' => 'operation.packages.insights', 'icon' => '🔎'],
                ],
            ],
            [
                'title' => 'Boxes',
                'items' => [
                    ['title' => 'Middo Boxes', 'route_name' => 'operation.middo-boxes.index', 'icon' => '📦'],
                ],
            ],
            [
                'title' => 'Logistics',
                'items' => [
                    ['title' => 'Custom runs', 'route_name' => 'operation.custom-runs.index', 'icon' => '📍'],
                    ['title' => 'Rider ops', 'route_name' => 'operation.riders.index', 'icon' => '🛵'],
                    ['title' => 'Areas & cities', 'route_name' => 'operation.areas.index', 'icon' => '🗺️'],
                    ['title' => 'Coverage', 'route_name' => 'operation.coverage.index', 'icon' => '📍'],
                    ['title' => 'Dispatch SLA', 'route_name' => 'operation.sla.index', 'icon' => '⏱️'],
                ],
            ],
            [
                'title' => 'Money',
                'items' => [
                    ['title' => 'Kitchen money', 'route_name' => 'operation.kitchen-money.index', 'icon' => '💰'],
                    ['title' => 'Rider money', 'route_name' => 'operation.rider-money.index', 'icon' => '🛵'],
                    ['title' => 'Rider cash handovers', 'route_name' => 'operation.cash-handovers', 'icon' => '💵'],
                ],
            ],
            [
                'title' => 'Support',
                'items' => [
                    ['title' => 'Complaints', 'route_name' => 'operation.complaints.index', 'icon' => '💬'],
                ],
            ],
        ];
    }

    /**
     * @return list<NavSection>
     */
    protected static function accounts(): array
    {
        return [
            [
                'title' => 'Overview',
                'items' => [
                    ['title' => 'Dashboard', 'route_name' => 'accounts.dashboard', 'icon' => '🏠'],
                    ['title' => 'Accounts Hub', 'route_name' => 'accounts.accounts.index', 'icon' => '📒'],
                ],
            ],
            [
                'title' => 'Cash & banks',
                'items' => [
                    ['title' => 'Middo cash', 'route_name' => 'accounts.middo-cash', 'icon' => '💵'],
                    ['title' => 'Cash positions', 'route_name' => 'accounts.cash-positions', 'icon' => '💰'],
                    ['title' => 'Bank ledger', 'route_name' => 'accounts.bank-ledger', 'icon' => '🏦'],
                    ['title' => 'Period P&L', 'route_name' => 'accounts.period-pnl', 'icon' => '📊'],
                    ['title' => 'Rider cash handovers', 'route_name' => 'accounts.cash-handovers', 'icon' => '🤝'],
                    ['title' => 'COD / Due recon', 'route_name' => 'accounts.cod-recon.index', 'icon' => '📊'],
                ],
            ],
            [
                'title' => 'Costs & payouts',
                'items' => [
                    ['title' => 'Operating costs', 'route_name' => 'accounts.operating-costs.index', 'icon' => '📈'],
                    ['title' => 'Kitchen money', 'route_name' => 'accounts.kitchen-money.index', 'icon' => '💰'],
                    ['title' => 'Rider money', 'route_name' => 'accounts.rider-money.index', 'icon' => '🛵'],
                ],
            ],
            [
                'title' => 'Partners',
                'items' => [
                    ['title' => 'Corporates', 'route_name' => 'accounts.corporates.index', 'icon' => '🏢'],
                ],
            ],
            [
                'title' => 'Orders',
                'items' => [
                    ['title' => 'Active orders', 'route_name' => 'accounts.orders.active', 'icon' => '📦'],
                    ['title' => 'Order History', 'route_name' => 'accounts.orders.history', 'icon' => '🗂️'],
                    ['title' => 'Search Order', 'route_name' => 'accounts.orders.search', 'icon' => '🔍'],
                ],
            ],
        ];
    }

    /**
     * @return list<NavSection>
     */
    protected static function kitchen(): array
    {
        return [
            [
                'title' => 'Overview',
                'items' => [
                    ['title' => 'Dashboard', 'route_name' => 'kitchen.dashboard', 'icon' => '🏠'],
                    ['title' => 'Alerts', 'route_name' => 'kitchen.alerts', 'icon' => '🔔'],
                ],
            ],
            [
                'title' => 'Orders',
                'items' => [
                    ['title' => 'Middo order groups', 'route_name' => 'kitchen.order-groups.middo', 'icon' => '📦'],
                    ['title' => 'My active orders', 'route_name' => 'kitchen.orders.active', 'icon' => '🔥'],
                    ['title' => 'My orders this month', 'route_name' => 'kitchen.orders.this-month', 'icon' => '📅'],
                    ['title' => 'Last 3 months', 'route_name' => 'kitchen.orders.last-three-months', 'icon' => '🗓️'],
                ],
            ],
            [
                'title' => 'Prep',
                'items' => [
                    ['title' => "Today's menus", 'route_name' => 'kitchen.menus.today', 'icon' => '🍽️'],
                    ['title' => 'Prep shopping list', 'route_name' => 'kitchen.prep.shopping-list', 'icon' => '🛒'],
                ],
            ],
            [
                'title' => 'Middo boxes',
                'items' => [
                    ['title' => 'Boxes at kitchen', 'route_name' => 'kitchen.middo-boxes.at-kitchen', 'icon' => '📦'],
                    ['title' => 'Incoming', 'route_name' => 'kitchen.middo-boxes.incoming', 'icon' => '📥'],
                ],
            ],
            [
                'title' => 'Account',
                'items' => [
                    ['title' => 'Account', 'route_name' => 'kitchen.account', 'icon' => '💼'],
                    ['title' => 'Profile', 'route_name' => 'kitchen.profile', 'icon' => '👤'],
                    ['title' => 'Cash handovers', 'route_name' => 'kitchen.cash-handovers', 'icon' => '💵'],
                ],
            ],
            [
                'title' => 'Support',
                'items' => [
                    ['title' => 'Complaints', 'route_name' => 'kitchen.complaints', 'icon' => '💬'],
                ],
            ],
        ];
    }

    /**
     * @return list<NavSection>
     */
    protected static function delivery(): array
    {
        return [
            [
                'title' => 'Overview',
                'items' => [
                    ['title' => 'Dashboard', 'route_name' => 'delivery.dashboard', 'icon' => '🏠'],
                    ['title' => 'Alerts', 'route_name' => 'delivery.alerts', 'icon' => '🔔'],
                ],
            ],
            [
                'title' => 'Runs',
                'items' => [
                    ['title' => 'Kitchen dispatches', 'route_name' => 'delivery.kitchen-dispatches', 'icon' => '🍳'],
                    ['title' => 'Middo boxes pending run', 'route_name' => 'delivery.middo-boxes.pending-run', 'icon' => '📦'],
                    ['title' => 'Custom runs', 'route_name' => 'delivery.custom-runs', 'icon' => '📍'],
                    ['title' => 'Delivered orders', 'route_name' => 'delivery.orders.delivered', 'icon' => '✅'],
                ],
            ],
            [
                'title' => 'Account',
                'items' => [
                    ['title' => 'Account', 'route_name' => 'delivery.account', 'icon' => '💼'],
                    ['title' => 'Cash handovers', 'route_name' => 'delivery.cash-handovers', 'icon' => '💵'],
                ],
            ],
        ];
    }

    /**
     * @return list<NavSection>
     */
    protected static function groundMarketing(): array
    {
        return [
            [
                'title' => 'Overview',
                'items' => [
                    ['title' => 'Dashboard', 'route_name' => 'marketing.dashboard', 'icon' => '🏠'],
                    ['title' => 'Companies', 'route_name' => 'marketing.companies.index', 'icon' => '🏢'],
                ],
            ],
        ];
    }

    /**
     * Corporate uses the public top header in-app; keep DB rows grouped for consistency.
     *
     * @return list<NavSection>
     */
    protected static function corporate(): array
    {
        return [
            [
                'title' => 'Overview',
                'items' => [
                    ['title' => 'Dashboard', 'route_name' => 'corporates.dashboard', 'icon' => '🏠'],
                    ['title' => 'Packages', 'route_name' => 'corporates.packages.index', 'icon' => '🍱'],
                    ['title' => 'Scheduled Orders', 'route_name' => 'corporates.orders.scheduled', 'icon' => '📅'],
                    ['title' => 'Order History', 'route_name' => 'corporates.orders.history', 'icon' => '🗂️'],
                ],
            ],
        ];
    }
}
