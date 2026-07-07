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

];
