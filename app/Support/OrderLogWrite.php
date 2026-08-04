<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class OrderLogWrite
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function opsIntervene(Order $order, User $actor, string $action, array $metadata = []): void
    {
        if (! Schema::hasTable('order_logs')) {
            return;
        }

        OrderLog::create([
            'order_id' => $order->id,
            'event' => 'ops_intervene',
            'performed_by' => $actor->id,
            'metadata' => array_merge([
                'action' => $action,
                'force' => false,
            ], $metadata),
        ]);
    }
}
