<?php

namespace Tests\Unit;

use App\Support\CorporateOrderPrepayment;
use App\Support\CouponService;
use PHPUnit\Framework\TestCase;

class CorporateMoneyAllocationTest extends TestCase
{
    public function test_prepay_is_allocated_on_post_discount_net_lines(): void
    {
        $lineTotals = [100, 100];
        $discountAmount = 1;
        $discountShares = (new CouponService)->allocateDiscount($lineTotals, $discountAmount);
        $this->assertSame(1, array_sum($discountShares));

        $netLineTotals = [];
        foreach ($lineTotals as $i => $gross) {
            $netLineTotals[] = max(0, $gross - ($discountShares[$i] ?? 0));
        }

        $payableCart = array_sum($netLineTotals);
        $this->assertSame(199, $payableCart);

        $allocations = CorporateOrderPrepayment::allocate($payableCart, $netLineTotals);

        $this->assertSame($payableCart, array_sum($allocations));
        foreach ($allocations as $i => $paid) {
            $this->assertSame($netLineTotals[$i], $paid, "line {$i} should be fully prepaid at net");
            $this->assertLessThanOrEqual($netLineTotals[$i], $paid);
        }
    }

    public function test_gross_line_allocation_overpays_discounted_line(): void
    {
        // Documents the bug fixed in OrderCheckoutModal: allocating prepaid on gross
        // lines while charging the post-discount cart desyncs amount_paid vs net due.
        $lineTotals = [100, 100];
        $discountShares = (new CouponService)->allocateDiscount($lineTotals, 1);
        $netLineTotals = [
            max(0, 100 - $discountShares[0]),
            max(0, 100 - $discountShares[1]),
        ];
        $payable = array_sum($netLineTotals);

        $buggy = CorporateOrderPrepayment::allocate($payable, $lineTotals);
        $fixed = CorporateOrderPrepayment::allocate($payable, $netLineTotals);

        $overpaid = false;
        foreach ($buggy as $i => $paid) {
            if ($paid > $netLineTotals[$i]) {
                $overpaid = true;
            }
        }
        $this->assertTrue($overpaid, 'gross allocation should overpay at least one discounted line');

        foreach ($fixed as $i => $paid) {
            $this->assertSame($netLineTotals[$i], $paid);
        }
    }

    public function test_half_prepay_never_exceeds_net_line(): void
    {
        $lineTotals = [200, 200, 200];
        $discountShares = (new CouponService)->allocateDiscount($lineTotals, 3);
        $netLineTotals = [];
        foreach ($lineTotals as $i => $gross) {
            $netLineTotals[] = max(0, $gross - ($discountShares[$i] ?? 0));
        }

        $charge = (int) round(array_sum($netLineTotals) * 0.5);
        $allocations = CorporateOrderPrepayment::allocate($charge, $netLineTotals);

        $this->assertSame($charge, array_sum($allocations));
        foreach ($allocations as $i => $paid) {
            $this->assertLessThanOrEqual($netLineTotals[$i], $paid);
        }
    }
}
