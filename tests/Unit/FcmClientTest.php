<?php

namespace Tests\Unit;

use App\Support\FcmClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmClientTest extends TestCase
{
    public function test_skips_when_credentials_missing(): void
    {
        config(['services.fcm.credentials' => storage_path('app/firebase/missing-service-account.json')]);

        $result = FcmClient::sendToTokens(['token-abc'], 'Title', 'Body');

        $this->assertTrue($result['skipped']);
        $this->assertSame(0, $result['sent']);
    }

    public function test_sends_via_http_v1_with_service_account(): void
    {
        Cache::flush();

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKeyPem);

        $path = storage_path('app/firebase/test-service-account.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode([
            'project_id' => 'middo-55888',
            'client_email' => 'firebase-adminsdk@middo-55888.iam.gserviceaccount.com',
            'private_key' => $privateKeyPem,
        ], JSON_THROW_ON_ERROR));

        config([
            'services.fcm.credentials' => $path,
            'services.fcm.project_id' => null,
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://fcm.googleapis.com/v1/projects/middo-55888/messages:send' => Http::response([
                'name' => 'projects/middo-55888/messages/0:1',
            ], 200),
        ]);

        $result = FcmClient::sendToTokens(
            ['device-token-1'],
            'Kitchen accepted your order',
            'Order #42 is being prepared.',
            ['type' => 'order_status', 'order_id' => '42'],
        );

        $this->assertFalse($result['skipped']);
        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, $result['failed']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://oauth2.googleapis.com/token'
                && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && filled($request['assertion']);
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/middo-55888/messages:send'
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && $request['message']['token'] === 'device-token-1'
                && $request['message']['notification']['title'] === 'Kitchen accepted your order'
                && $request['message']['data']['order_id'] === '42';
        });

        @unlink($path);
    }
}
