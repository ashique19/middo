<?php

namespace App\Support;

use App\Models\ApiIdempotencyKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ApiIdempotency
{
    public static function keyFrom(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $request->input('idempotency_key');

        if (! is_string($key)) {
            return null;
        }

        $key = trim($key);

        return $key !== '' && strlen($key) <= 64 ? $key : null;
    }

    public static function find(Request $request): ?JsonResponse
    {
        $key = self::keyFrom($request);
        $userId = (int) ($request->user()?->id ?? 0);
        if ($key === null || $userId < 1 || ! Schema::hasTable('api_idempotency_keys')) {
            return null;
        }

        $row = ApiIdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();

        if (! $row) {
            return null;
        }

        return response()->json($row->response_body, (int) $row->status_code);
    }

    public static function store(Request $request, JsonResponse $response): void
    {
        $key = self::keyFrom($request);
        $userId = (int) ($request->user()?->id ?? 0);
        if ($key === null || $userId < 1 || ! Schema::hasTable('api_idempotency_keys')) {
            return;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return;
        }

        $body = json_decode($response->getContent() ?: '[]', true);
        if (! is_array($body)) {
            $body = ['message' => 'OK'];
        }

        ApiIdempotencyKey::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'key' => $key,
            ],
            [
                'route' => $request->path(),
                'status_code' => $status,
                'response_body' => $body,
            ],
        );
    }
};
