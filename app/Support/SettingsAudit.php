<?php

namespace App\Support;

use App\Models\SettingChangeLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SettingsAudit
{
    public const SOURCE_ADMIN_SETTINGS = 'admin.settings';

    public const SOURCE_ADMIN_BANK = 'admin.bank_accounts';

    /**
     * Human labels for known setting keys.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            MiddoSettings::KEY_ACCEPT_WINDOW_MINUTES => 'Accept window (minutes)',
            MiddoSettings::KEY_ACCEPT_WINDOW_WARN_MINUTES => 'Accept window warn (minutes)',
            MiddoSettings::KEY_ACCEPT_WINDOW_STARTS_AT => 'Accept window starts at',
            MiddoSettings::KEY_ORDER_CUTOFF_TIME => 'Daily order cutoff',
            MiddoSettings::KEY_MAX_ORDER_QTY_ALLOWED => 'Max order quantity',
            MiddoSettings::KEY_AUTO_GROUP_QUANTITY => 'Auto group max quantity',
            MiddoSettings::KEY_MID_RUN_RESCUE => 'Mid-run rescue commission',
            MiddoSettings::KEY_KITCHEN_TO_OPS_VIA_RIDER => 'Kitchen → ops via rider',
            MiddoSettings::KEY_VAT_RATE_PCT => 'VAT rate %',
            MiddoSettings::KEY_EPS_FEE_RATES => 'EPS fee rates',
            MiddoSettings::KEY_DEFAULT_EPS_BANK_ACCOUNT_ID => 'Default EPS bank account',
            MiddoSettings::KEY_FULL_PREPAY_FROM_ACTIVE_ORDERS => 'Full prepay from active orders',
            MiddoSettings::KEY_RIDER_UNCLAIMED_AGE_WARN_MINUTES => 'Rider unclaimed age warn (minutes)',
            'kitchen.tier_defaults.silver.allowed_open_groups' => 'Kitchen tier silver open groups',
            'kitchen.tier_defaults.gold.allowed_open_groups' => 'Kitchen tier gold open groups',
            'kitchen.tier_defaults.platinum.allowed_open_groups' => 'Kitchen tier platinum open groups',
            'delivery.commission.corporate_to_kitchen' => 'Commission corporate → kitchen',
            'delivery.commission.kitchen_to_ops' => 'Commission kitchen → ops',
            'delivery.commission.ops_to_kitchen' => 'Commission ops → kitchen',
            'delivery.commission.custom' => 'Commission custom run',
        ];
    }

    public static function labelFor(string $key): string
    {
        return self::labels()[$key] ?? str($key)->replace(['.', '_'], ' ')->headline()->toString();
    }

    /**
     * @param  list<array{key: string, old: mixed, new: mixed}>  $changes
     */
    public static function recordBatch(
        array $changes,
        ?int $actorId = null,
        string $source = self::SOURCE_ADMIN_SETTINGS,
        ?string $summary = null,
    ): ?SettingChangeLog {
        try {
            if (! Schema::hasTable('setting_change_logs') || $changes === []) {
                return null;
            }

            $normalized = [];
            foreach ($changes as $change) {
                $key = (string) ($change['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $old = self::stringify($change['old'] ?? null);
                $new = self::stringify($change['new'] ?? null);
                if ($old === $new) {
                    continue;
                }
                $normalized[] = [
                    'key' => $key,
                    'label' => self::labelFor($key),
                    'old' => $old,
                    'new' => $new,
                ];
            }

            if ($normalized === []) {
                return null;
            }

            $count = count($normalized);
            $summary ??= $count === 1
                ? 'Updated '.$normalized[0]['label']
                : "Updated {$count} settings";

            return SettingChangeLog::create([
                'actor_id' => $actorId ?? Auth::id(),
                'source' => $source,
                'summary' => mb_substr($summary, 0, 500),
                'changes' => $normalized,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    protected static function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
