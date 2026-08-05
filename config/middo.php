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
    | Accept Window SLA Warn
    |--------------------------------------------------------------------------
    |
    | Minutes before window close when kitchens should be warned about open
    | pool groups (in-app alerts + Middo order groups badge).
    |
    */

    'accept_window_warn_minutes' => (int) env('ACCEPT_WINDOW_WARN_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Rider unclaimed age warn
    |--------------------------------------------------------------------------
    |
    | Minutes since kitchen dispatch before a packed order with no rider is
    | treated as aging on the Riders / Coverage boards (N2).
    |
    */

    'rider_unclaimed_age_warn_minutes' => (int) env('RIDER_UNCLAIMED_AGE_WARN_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Kitchen → ops empty-box via rider (N5)
    |--------------------------------------------------------------------------
    |
    | When false (default), kitchen "Send to Middo warehouse" teleports the box.
    | When true, kitchen can also assign a rider (books kitchen_to_ops commission).
    |
    */
    'kitchen_to_ops_via_rider' => (bool) env('KITCHEN_TO_OPS_VIA_RIDER', false),

    /*
    |--------------------------------------------------------------------------
    | Food VAT (inclusive)
    |--------------------------------------------------------------------------
    |
    | Default statutory rate for food businesses. Admin Settings can edit.
    | Applied to food only; unbundled for middo_rest / tax reporting.
    |
    */
    'vat_rate_pct' => (float) env('MIDDO_VAT_RATE_PCT', 5),

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
    | Delivery / rider commission defaults (non-lunch runs)
    |--------------------------------------------------------------------------
    |
    | Lunch kitchen→corporate uses menu_items.delivery_commission.
    | These defaults apply to box legs and custom runs (৳ per box / per run).
    | Per-rider overrides live on users.rider_commission_overrides.
    |
    */

    'delivery_commission_defaults' => [
        'corporate_to_kitchen' => (int) env('DELIVERY_COMMISSION_CORPORATE_TO_KITCHEN', 30),
        'kitchen_to_ops' => (int) env('DELIVERY_COMMISSION_KITCHEN_TO_OPS', 25),
        'ops_to_kitchen' => (int) env('DELIVERY_COMMISSION_OPS_TO_KITCHEN', 25),
        'custom' => (int) env('DELIVERY_COMMISSION_CUSTOM', 40),
        'mid_run_rescue' => (int) env('DELIVERY_COMMISSION_MID_RUN_RESCUE', 0),
    ],

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
