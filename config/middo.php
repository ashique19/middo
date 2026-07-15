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
    | Kitchen Dispatch Deadline
    |--------------------------------------------------------------------------
    |
    | Minutes before the order delivery window when kitchen dispatch is due.
    | Used for the active-orders countdown timer.
    |
    */

    'dispatch_deadline_minutes_before' => (int) env('DISPATCH_DEADLINE_MINUTES_BEFORE', 60),

];
