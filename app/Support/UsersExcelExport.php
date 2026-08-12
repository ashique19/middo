<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsersExcelExport
{
    /**
     * @param  Collection<int, User>|iterable<int, User>  $users
     * @param  'delivery'|'kitchen'|string|null  $roleType
     */
    public static function download(
        iterable $users,
        ?string $roleType = null,
        string $filename = 'users.csv',
    ): StreamedResponse {
        $filename = str_ends_with(strtolower($filename), '.csv')
            ? $filename
            : $filename.'.csv';

        return response()->streamDownload(function () use ($users, $roleType) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens Bengali/Unicode cleanly.
            fwrite($out, "\xEF\xBB\xBF");

            $isDelivery = $roleType === 'delivery';
            $isKitchen = $roleType === 'kitchen';

            if ($isDelivery) {
                fputcsv($out, [
                    'Name',
                    'Phone',
                    'Email',
                    'Areas',
                    'Status',
                    'Created At',
                ]);
            } elseif ($isKitchen) {
                fputcsv($out, [
                    'Name',
                    'Phone',
                    'Email',
                    'Area',
                    'Status',
                    'Created At',
                ]);
            } else {
                fputcsv($out, [
                    'Name',
                    'Phone',
                    'Email',
                    'Role',
                    'Area',
                    'Status',
                    'Created At',
                ]);
            }

            foreach ($users as $user) {
                if (! $user instanceof User) {
                    continue;
                }

                $user->loadMissing(['role', 'area', 'areas']);
                $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                $createdAt = optional($user->created_at)?->timezone('Asia/Dhaka')->format('Y-m-d H:i') ?? '';

                if ($isDelivery) {
                    $areaNames = $user->areas->isNotEmpty()
                        ? $user->areas->pluck('name')->all()
                        : array_filter([$user->area?->name]);

                    fputcsv($out, [
                        $name,
                        $user->mobile ?? '',
                        $user->email ?? '',
                        implode(', ', $areaNames),
                        $user->status ?? '',
                        $createdAt,
                    ]);

                    continue;
                }

                if ($isKitchen) {
                    fputcsv($out, [
                        $name,
                        $user->mobile ?? '',
                        $user->email ?? '',
                        $user->area_name ?: ($user->area?->name ?? ''),
                        $user->status ?? '',
                        $createdAt,
                    ]);

                    continue;
                }

                fputcsv($out, [
                    $name,
                    $user->mobile ?? '',
                    $user->email ?? '',
                    $user->role->name ?? '',
                    $user->area_name ?: ($user->area?->name ?? ''),
                    $user->status ?? '',
                    $createdAt,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
