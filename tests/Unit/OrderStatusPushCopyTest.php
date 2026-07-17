<?php

namespace Tests\Unit;

use App\Support\OrderStatusPushCopy;
use PHPUnit\Framework\TestCase;

class OrderStatusPushCopyTest extends TestCase
{
    public function test_maps_kitchen_dispatch_and_delivery_statuses(): void
    {
        $processing = OrderStatusPushCopy::forStatus('processing', 42, 'Beef Tehari');
        $this->assertSame('Kitchen accepted your order', $processing['title']);
        $this->assertStringContainsString('#42', $processing['body']);

        $dispatch = OrderStatusPushCopy::forStatus('on_the_way_to_delivery', 42, null);
        $this->assertSame('Your order is on the way', $dispatch['title']);

        $delivered = OrderStatusPushCopy::forStatus('delivered', 7, 'Thali');
        $this->assertSame('Order delivered', $delivered['title']);

        $settled = OrderStatusPushCopy::forStatus('delivered_and_paid', 7, 'Thali');
        $this->assertSame('Delivery complete', $settled['title']);

        $packed = OrderStatusPushCopy::forStatus('packed', 9, null);
        $this->assertSame('Your order is packed', $packed['title']);

        $this->assertNull(OrderStatusPushCopy::forStatus('pending', 1));
    }
}
