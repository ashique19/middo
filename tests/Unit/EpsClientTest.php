<?php

namespace Tests\Unit;

use App\Support\Payments\Eps\EpsClient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EpsClientTest extends TestCase
{
    #[Test]
    public function it_generates_eps_x_hash_as_base64_hmac_sha512(): void
    {
        // Example from EPS Sandbox Merchant API Integration Guide V4.
        $client = new EpsClient([
            'hash_key' => 'SFNLQHJlY2lwZXdhbGEjYTc3Zi1mOTQ5NWZhY2M2ZTZuZXQ=',
        ]);

        $this->assertSame(
            'BKXUD5z0NQgPDQMcZuPL2dSUwo5oSdvBpzz2xbkxikB7KfYV0kZIF8sW6udvSqOTZNUJ5VHnMTSJP3oxDABpJQ==',
            $client->generateHash('dt_merchant@eps.com.bd')
        );
    }
}
