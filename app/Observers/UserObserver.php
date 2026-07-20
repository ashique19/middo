<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserLog;
use App\Support\UserAudit;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    public function created(User $user): void
    {
        UserAudit::record(
            user: $user,
            event: UserLog::EVENT_CREATED,
            metadata: [
                'snapshot' => $this->snapshot($user),
            ],
            performedBy: Auth::id() ?? $user->id,
        );
    }

    public function updated(User $user): void
    {
        $changes = collect($user->getChanges())
            ->except(['updated_at', 'remember_token'])
            ->all();

        if ($changes === []) {
            return;
        }

        $diff = collect($changes)
            ->mapWithKeys(function ($value, $key) use ($user) {
                $from = $user->getOriginal($key);
                $to = $value;

                if ($key === 'password') {
                    $from = '[redacted]';
                    $to = '[changed]';
                }

                return [
                    $key => [
                        'from' => $from,
                        'to' => $to,
                    ],
                ];
            })
            ->all();

        $event = $this->resolveEvent($diff);

        UserAudit::record(
            user: $user,
            event: $event,
            metadata: ['changes' => $diff],
            performedBy: Auth::id(),
        );
    }

    public function deleting(User $user): void
    {
        UserAudit::record(
            user: $user,
            event: UserLog::EVENT_DELETED,
            metadata: [
                'snapshot' => $this->snapshot($user),
            ],
            performedBy: Auth::id(),
        );
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $diff
     */
    protected function resolveEvent(array $diff): string
    {
        $keys = array_keys($diff);

        if ($keys === ['status']) {
            return UserLog::EVENT_STATUS_CHANGED;
        }

        if ($keys === ['password']) {
            return UserLog::EVENT_PASSWORD_CHANGED;
        }

        return UserLog::EVENT_UPDATED;
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(User $user): array
    {
        return $user->only([
            'first_name',
            'last_name',
            'company_name',
            'mobile',
            'email',
            'role_id',
            'status',
            'is_mobile_verified',
            'address',
            'city_id',
            'area_id',
            'balance',
        ]);
    }
}
