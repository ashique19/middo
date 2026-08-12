<?php

namespace App\Support;

use App\Models\Area;
use App\Models\City;
use App\Models\Role;
use App\Models\UserLog;

/**
 * Turn UserLog metadata into short plain-language lines for admins.
 */
class UserAuditPresenter
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @return list<string>
     */
    public static function metadataLines(?array $metadata): array
    {
        if ($metadata === null || $metadata === []) {
            return [];
        }

        $lines = [];

        if (isset($metadata['changes']) && is_array($metadata['changes'])) {
            foreach ($metadata['changes'] as $field => $change) {
                if (! is_array($change)) {
                    continue;
                }
                $lines[] = sprintf(
                    '%s changed from %s to %s',
                    self::fieldLabel((string) $field),
                    self::displayValue((string) $field, $change['from'] ?? null),
                    self::displayValue((string) $field, $change['to'] ?? null),
                );
            }
        }

        if (isset($metadata['snapshot']) && is_array($metadata['snapshot'])) {
            $parts = [];
            foreach ($metadata['snapshot'] as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                if (in_array((string) $field, ['password', 'remember_token'], true)) {
                    continue;
                }
                $parts[] = self::fieldLabel((string) $field).': '.self::displayValue((string) $field, $value);
            }
            if ($parts !== []) {
                $lines[] = 'Details — '.implode(' · ', $parts);
            }
        }

        foreach ($metadata as $key => $value) {
            if (in_array($key, ['changes', 'snapshot'], true)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                $lines[] = self::fieldLabel((string) $key).': '.json_encode($value, JSON_UNESCAPED_UNICODE);
                continue;
            }
            $lines[] = self::fieldLabel((string) $key).': '.self::displayValue((string) $key, $value);
        }

        return $lines;
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            UserAudit::SOURCE_WEB => 'Website',
            UserAudit::SOURCE_API => 'API',
            UserAudit::SOURCE_ADMIN => 'Admin panel',
            UserAudit::SOURCE_OPERATION => 'Operations',
            UserAudit::SOURCE_CORPORATE_MOBILE => 'Corporate app',
            UserAudit::SOURCE_KITCHEN => 'Kitchen app',
            UserAudit::SOURCE_DELIVERY => 'Rider app',
            UserAudit::SOURCE_SYSTEM => 'System',
            default => $source ? ucfirst(str_replace('_', ' ', $source)) : 'System',
        };
    }

    public static function eventLabel(string $event): string
    {
        return match ($event) {
            UserLog::EVENT_LOGIN => 'Login',
            UserLog::EVENT_LOGOUT => 'Logout',
            UserLog::EVENT_LOGIN_FAILED => 'Login failed',
            UserLog::EVENT_LOGIN_BLOCKED => 'Login blocked',
            UserLog::EVENT_CREATED => 'Account created',
            UserLog::EVENT_UPDATED => 'Profile updated',
            UserLog::EVENT_DELETED => 'Account deleted',
            UserLog::EVENT_STATUS_CHANGED => 'Status changed',
            UserLog::EVENT_PASSWORD_CHANGED => 'Password changed',
            UserLog::EVENT_PASSWORD_RESET => 'Password reset',
            default => ucfirst(str_replace('_', ' ', $event)),
        };
    }

    protected static function fieldLabel(string $field): string
    {
        return match ($field) {
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'company_name' => 'Company',
            'mobile' => 'Phone',
            'email' => 'Email',
            'role_id' => 'Role',
            'status' => 'Status',
            'is_mobile_verified' => 'Mobile verified',
            'address' => 'Address',
            'city_id' => 'City',
            'area_id' => 'Area',
            'balance' => 'Balance',
            'password' => 'Password',
            'remember' => 'Stay signed in',
            'device_name' => 'Device',
            'reason' => 'Reason',
            'guard' => 'Login channel',
            'note' => 'Note',
            'role' => 'Role',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }

    protected static function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === 'password') {
            if ($value === '[redacted]' || $value === '[changed]') {
                return $value === '[changed]' ? 'new password' : 'hidden';
            }

            return 'hidden';
        }

        if ($field === 'reason') {
            return match ((string) $value) {
                'inactive' => 'Account not active',
                'wrong_role' => 'Wrong account type for this login',
                default => ucfirst(str_replace('_', ' ', (string) $value)),
            };
        }

        if ($field === 'remember' || $field === 'is_mobile_verified') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        if ($field === 'balance' && is_numeric($value)) {
            return '৳'.number_format((int) $value);
        }

        if ($field === 'role_id' && is_numeric($value)) {
            $name = Role::query()->whereKey((int) $value)->value('name');

            return $name ? ucfirst((string) $name).' (ID '.(int) $value.')' : 'ID '.(int) $value;
        }

        if ($field === 'city_id' && is_numeric($value)) {
            $name = City::query()->whereKey((int) $value)->value('name');

            return $name ? $name.' (ID '.(int) $value.')' : 'ID '.(int) $value;
        }

        if ($field === 'area_id' && is_numeric($value)) {
            $name = Area::query()->whereKey((int) $value)->value('name');

            return $name ? $name.' (ID '.(int) $value.')' : 'ID '.(int) $value;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—';
    }
}
