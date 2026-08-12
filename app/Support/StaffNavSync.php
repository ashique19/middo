<?php

namespace App\Support;

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class StaffNavSync
{
    /**
     * Rebuild each role's desktop nav tree from StaffNavStructure.
     * Idempotent: matches leaf rows by route_name + role_id.
     */
    public static function syncAll(): void
    {
        foreach (StaffNavStructure::roleNames() as $roleName) {
            self::syncRole($roleName);
        }
    }

    public static function syncRole(string $roleName): void
    {
        $roleId = Role::query()->where('name', $roleName)->value('id');
        if (! $roleId) {
            return;
        }

        $sections = StaffNavStructure::sectionsFor($roleName);
        if ($sections === []) {
            return;
        }

        DB::transaction(function () use ($roleId, $sections) {
            $keptParentIds = [];
            $keptLeafIds = [];

            foreach ($sections as $sectionIndex => $section) {
                $parent = Nav::query()
                    ->where('role_id', $roleId)
                    ->whereNull('parent_id')
                    ->whereNull('route_name')
                    ->where('title', $section['title'])
                    ->first();

                if (! $parent) {
                    $parent = Nav::create([
                        'title' => $section['title'],
                        'route_name' => null,
                        'icon' => null,
                        'order' => $sectionIndex + 1,
                        'role_id' => $roleId,
                        'parent_id' => null,
                    ]);
                } else {
                    $parent->update([
                        'order' => $sectionIndex + 1,
                        'icon' => null,
                    ]);
                }

                $keptParentIds[] = (int) $parent->id;

                foreach ($section['items'] as $itemIndex => $item) {
                    $leaf = Nav::query()
                        ->where('role_id', $roleId)
                        ->where('route_name', $item['route_name'])
                        ->orderByRaw('CASE WHEN parent_id IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('id')
                        ->first();

                    $payload = [
                        'title' => $item['title'],
                        'route_name' => $item['route_name'],
                        'icon' => $item['icon'] ?? null,
                        'order' => $itemIndex + 1,
                        'role_id' => $roleId,
                        'parent_id' => $parent->id,
                    ];

                    if ($leaf) {
                        $leaf->update($payload);
                        $keptLeafIds[] = (int) $leaf->id;

                        // Drop duplicate leaf rows for the same route (legacy flat + nested).
                        Nav::query()
                            ->where('role_id', $roleId)
                            ->where('route_name', $item['route_name'])
                            ->where('id', '!=', $leaf->id)
                            ->delete();
                    } else {
                        $created = Nav::create($payload);
                        $keptLeafIds[] = (int) $created->id;
                    }
                }
            }

            // Remove obsolete section parents (and any leftover children).
            $obsoleteParents = Nav::query()
                ->where('role_id', $roleId)
                ->whereNull('parent_id')
                ->whereNull('route_name')
                ->whereNotIn('id', $keptParentIds)
                ->pluck('id');

            if ($obsoleteParents->isNotEmpty()) {
                Nav::query()->whereIn('parent_id', $obsoleteParents)->delete();
                Nav::query()->whereIn('id', $obsoleteParents)->delete();
            }

            // Remove leftover top-level links not part of the structure.
            Nav::query()
                ->where('role_id', $roleId)
                ->whereNull('parent_id')
                ->whereNotNull('route_name')
                ->whereNotIn('id', $keptLeafIds)
                ->delete();

            // Remove orphan children under kept parents that are no longer listed.
            Nav::query()
                ->where('role_id', $roleId)
                ->whereIn('parent_id', $keptParentIds)
                ->whereNotIn('id', $keptLeafIds)
                ->delete();
        });
    }
}
