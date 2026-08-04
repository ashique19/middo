<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Order Quantity Per Day
    |--------------------------------------------------------------------------
    |
    | The total number of meals a corporate user may order for a single
    | delivery date across all active orders.
    |
    */

    'max_order_qty_allowed' => (int) env('MAX_ORDER_QTY_ALLOWED', 5),

    /*
    |--------------------------------------------------------------------------
    | Auto Meal Group Quantity
    |--------------------------------------------------------------------------
    |
    | Maximum total meal quantity allowed in a single auto-generated group.
    |
    */

    'auto_meal_group_quantity' => (int) env('AUTO_MEAL_GROUP_QUANTITY', 10),

    /*
    |--------------------------------------------------------------------------
    | Kitchen Accept Window
    |--------------------------------------------------------------------------
    |
    | Minutes around a group's schedule during which a kitchen may accept it.
    | Admin Settings can override this at runtime via the settings table.
    |
    */

    'accept_window_minutes' => (int) env('ACCEPT_WINDOW_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Kitchen Tier Defaults (allowed concurrent open groups)
    |--------------------------------------------------------------------------
    |
    | Used when Settings rows are missing. Admin Settings overrides per tier;
    | activation copies the tier default onto the kitchen (overridable later).
    |
    */

    'kitchen_tier_defaults' => [
        'silver' => (int) env('KITCHEN_TIER_SILVER_OPEN_GROUPS', 1),
        'gold' => (int) env('KITCHEN_TIER_GOLD_OPEN_GROUPS', 2),
        'platinum' => (int) env('KITCHEN_TIER_PLATINUM_OPEN_GROUPS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kitchen Dispatch Deadline
    |--------------------------------------------------------------------------
    |
    | Minutes before the order delivery window when kitchen dispatch is due.
    | Used for the active-orders countdown timer.
    |
    */

    'dispatch_deadline_minutes_before' => (int) env('DISPATCH_DEADLINE_MINUTES_BEFORE', 60),

    /*
    |--------------------------------------------------------------------------
    | Corporate Order Cutoff (place / edit / cancel)
    |--------------------------------------------------------------------------
    |
    | On the delivery calendar day (Asia/Dhaka), orders may be placed, edited,
    | or cancelled only before this wall-clock time.
    |
    */

    'order_cutoff_timezone' => env('ORDER_CUTOFF_TIMEZONE', 'Asia/Dhaka'),
    'order_cutoff_hour' => (int) env('ORDER_CUTOFF_HOUR', 15),
    'order_cutoff_minute' => (int) env('ORDER_CUTOFF_MINUTE', 28),

];
