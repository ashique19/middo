<?php

namespace Tests\Unit;

use App\Support\MimSms;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MimSmsTest extends TestCase
{
    public function test_send_skips_http_when_app_debug_is_true(): void
    {
        config([
            'app.debug' => true,
            'services.mimsms.api_key' => 'test-key',
            'services.mimsms.user_name' => 'test-user',
            'services.mimsms.sender_name' => 'MIDDO',
            'services.mimsms.base_url' => 'https://mimsms.test/send',
        ]);

        Http::fake([
            'https://mimsms.test/send' => Http::response(['status' => 'OK'], 200),
        ]);

        $this->assertFalse(MimSms::send('01710123456', 'Test message'));

        Http::assertNothingSent();
    }

    public function test_send_posts_when_app_debug_is_false(): void
    {
        config([
            'app.debug' => false,
            'services.mimsms.api_key' => 'test-key',
            'services.mimsms.user_name' => 'test-user',
            'services.mimsms.sender_name' => 'MIDDO',
            'services.mimsms.base_url' => 'https://mimsms.test/send',
        ]);

        Http::fake([
            'https://mimsms.test/send' => Http::response(['status' => 'OK'], 200),
        ]);

        $this->assertTrue(MimSms::send('01710123456', 'Test message'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'mimsms.test')
                && ($request['mobileNumber'] ?? null) === '8801710123456'
                && ($request['message'] ?? null) === 'Test message';
        });
    }
}
