<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class UserAudit
{
    public const SOURCE_WEB = 'web';

    public const SOURCE_API = 'api';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_OPERATION = 'operation';

    public const SOURCE_CORPORATE_MOBILE = 'corporate_mobile';

    public const SOURCE_KITCHEN = 'kitchen';

    public const SOURCE_DELIVERY = 'delivery';

    public const SOURCE_SYSTEM = 'system';

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function record(
        ?User $user,
        string $event,
        ?string $source = null,
        ?array $metadata = null,
        ?int $performedBy = null,
        ?Request $request = null,
    ): void {
        try {
            $request ??= request();
            $actorId = $performedBy
                ?? Auth::id()
                ?? $user?->id;

            UserLog::create([
                'user_id' => $user?->id,
                'performed_by' => $actorId,
                'event' => $event,
                'source' => $source ?? self::resolveSource($request),
                'ip_address' => $request?->ip(),
                'user_agent' => self::truncateUserAgent($request?->userAgent()),
                'metadata' => $metadata,
            ]);
        } catch (Throwable $e) {
            // Audit must never break the primary request.
            report($e);
        }
    }

    public static function resolveSource(?Request $request = null): string
    {
        $request ??= request();

        if (! $request) {
            return self::SOURCE_SYSTEM;
        }

        if ($request->is('api/corporate', 'api/corporate/*')) {
            return self::SOURCE_CORPORATE_MOBILE;
        }

        if ($request->is('api', 'api/*')) {
            return self::SOURCE_API;
        }

        if ($request->is('admin', 'admin/*')) {
            return self::SOURCE_ADMIN;
        }

        if ($request->is('operation', 'operation/*')) {
            return self::SOURCE_OPERATION;
        }

        if ($request->is('kitchen', 'kitchen/*')) {
            return self::SOURCE_KITCHEN;
        }

        if ($request->is('delivery', 'delivery/*')) {
            return self::SOURCE_DELIVERY;
        }

        return self::SOURCE_WEB;
    }

    protected static function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return mb_substr($userAgent, 0, 512);
    }
}
