<?php

namespace App\Support;

use App\Models\Area;
use App\Models\User;

class StaffRiders
{
    /**
     * Active delivery riders for ops assignment pickers.
     * Area-matched riders sort first when $preferAreaId is set.
     *
     * @return list<array{id:int,name:string,areas_label:string,search:string}>
     */
    public static function pickerOptions(?int $preferAreaId = null): array
    {
        return User::query()
            ->with(['role', 'areas', 'area'])
            ->whereHas('role', fn ($query) => $query->where('name', 'delivery'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->sortBy(function (User $user) use ($preferAreaId) {
                if ($preferAreaId === null) {
                    return 1;
                }

                return $user->servesArea($preferAreaId) ? 0 : 1;
            })
            ->values()
            ->map(function (User $user) {
                $areaNames = self::coverageAreaNames($user);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'areas_label' => $areaNames === [] ? 'No coverage areas' : implode(', ', $areaNames),
                    'search' => strtolower(trim($user->name.' '.implode(' ', $areaNames))),
                ];
            })
            ->all();
    }

    public static function assertActiveDelivery(User $rider): User
    {
        $rider->loadMissing('role');
        if ($rider->role?->name !== 'delivery' || $rider->status !== 'active') {
            throw new \RuntimeException('Assignee must be an active delivery rider.');
        }

        return $rider;
    }

    /**
     * @return list<string>
     */
    public static function coverageAreaNames(User $user): array
    {
        $ids = $user->serviceAreaIds();
        if ($ids === []) {
            return [];
        }

        if ($user->relationLoaded('areas') && $user->areas->isNotEmpty()) {
            $names = $user->areas
                ->whereIn('id', $ids)
                ->sortBy('name')
                ->pluck('name')
                ->map(fn ($name) => (string) $name)
                ->values()
                ->all();

            if ($names !== []) {
                return $names;
            }
        }

        return Area::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }
}
