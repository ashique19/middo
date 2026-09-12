<?php

namespace App\Support;

use App\Models\StaffAlert;
use App\Models\User;

class OperationApiPresenter
{
    public static function user(User $user): array
    {
        $user->loadMissing('role');

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'role' => $user->role?->name,
            'status' => $user->status,
        ];
    }

    public static function dashboardTile(string $key, string $label, int $count): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
        ];
    }

    public static function alert(StaffAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'title' => $alert->title,
            'body' => $alert->body,
            'order_group_id' => $alert->order_group_id,
            'meta' => $alert->meta ?? [],
            'read_at' => $alert->read_at?->toIso8601String(),
            'is_unread' => $alert->read_at === null,
            'created_at' => $alert->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<\App\Models\StaffAlert>  $paginator
     * @return array<string, mixed>
     */
    public static function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
