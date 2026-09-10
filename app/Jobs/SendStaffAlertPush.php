<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\StaffAlert;
use App\Support\FcmClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SendStaffAlertPush implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $alertId,
    ) {}

    public function handle(): void
    {
        try {
            $this->send();
        } catch (Throwable $e) {
            Log::warning('Staff alert push failed', [
                'alert_id' => $this->alertId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function send(): void
    {
        if (! Schema::hasTable('device_tokens') || ! Schema::hasTable('staff_alerts')) {
            return;
        }

        $alert = StaffAlert::query()->find($this->alertId);
        if (! $alert || ! $alert->user_id) {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $alert->user_id)
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            Log::debug('No device tokens for staff alert push', [
                'alert_id' => $alert->id,
                'user_id' => $alert->user_id,
                'type' => $alert->type,
            ]);

            return;
        }

        $result = FcmClient::sendToTokens(
            $tokens,
            (string) $alert->title,
            (string) ($alert->body ?: $alert->title),
            array_filter([
                'type' => 'staff_alert',
                'alert_id' => (string) $alert->id,
                'alert_type' => (string) $alert->type,
                'order_group_id' => $alert->order_group_id !== null
                    ? (string) $alert->order_group_id
                    : '',
                'order_id' => (string) ($alert->meta['order_id'] ?? $alert->meta['run_id'] ?? ''),
                'path' => self::pathForAlert($alert),
                'deep_link' => self::deepLinkForAlert($alert),
            ], fn ($v) => $v !== null && $v !== ''),
            'middo_staff_alerts',
        );

        Log::info('Staff alert push dispatched', [
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'type' => $alert->type,
            'result' => $result,
        ]);
    }

    protected static function pathForAlert(\App\Models\StaffAlert $alert): string
    {
        $orderId = $alert->meta['order_id'] ?? $alert->meta['run_id'] ?? null;
        return match ($alert->type) {
            \App\Models\StaffAlert::TYPE_LUNCH_DISPATCH,
            'lunch_dispatch',
            'run_assigned',
            \App\Models\StaffAlert::TYPE_CUSTOM_RUN,
            'custom_run' => $orderId ? '/runs/'.$orderId : '/runs',
            \App\Models\StaffAlert::TYPE_OPS_TO_KITCHEN_BOX,
            \App\Models\StaffAlert::TYPE_EMPTY_BOX_PICKUP,
            \App\Models\StaffAlert::TYPE_KITCHEN_TO_OPS_BOX,
            \App\Models\StaffAlert::TYPE_KITCHEN_BOX_REQUEST,
            'ops_to_kitchen_box',
            'empty_box_pickup',
            'kitchen_to_ops_box' => '/boxes',
            default => '/alerts',
        };
    }

    protected static function deepLinkForAlert(\App\Models\StaffAlert $alert): string
    {
        $path = ltrim(self::pathForAlert($alert), '/');

        return 'middo-delivery://'.$path;
    }
}
