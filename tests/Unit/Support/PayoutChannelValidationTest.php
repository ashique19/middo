<?php

namespace Tests\Unit\Support;

use App\Support\BdBanks;
use App\Support\PayoutChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayoutChannelValidationTest extends TestCase
{
    #[Test]
    public function bank_catalog_loads_with_cities_and_branches(): void
    {
        // Empty DB falls back to database/data/bd_banks.json.
        BdBanks::forgetCache();
        $banks = BdBanks::bankNames();
        $this->assertNotEmpty($banks);
        $this->assertContains('Dutch-Bangla Bank Limited', $banks);

        $cities = BdBanks::citiesFor('Dutch-Bangla Bank Limited');
        $this->assertContains('Dhaka', $cities);

        $branches = BdBanks::branchesFor('Dutch-Bangla Bank Limited', 'Dhaka');
        $this->assertContains('Abdullahpur Branch', $branches);
        $this->assertTrue(BdBanks::isValidSelection(
            'Dutch-Bangla Bank Limited',
            'Dhaka',
            'Abdullahpur Branch'
        ));
    }

    #[Test]
    public function bank_details_require_valid_selection_and_formats(): void
    {
        PayoutChannel::assertValid(PayoutChannel::BANK, [
            'bank_name' => 'Dutch-Bangla Bank Limited',
            'city' => 'Dhaka',
            'branch' => 'Abdullahpur Branch',
            'account_name' => 'A. Kitchen-Owner',
            'account_number' => '1234567890',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        PayoutChannel::assertValid(PayoutChannel::BANK, [
            'bank_name' => 'Dutch-Bangla Bank Limited',
            'city' => 'Dhaka',
            'branch' => 'Abdullahpur Branch',
            'account_name' => 'Kitchen#1',
            'account_number' => '1234567890',
        ]);
    }

    #[Test]
    public function bkash_and_nagad_store_personal_number_only(): void
    {
        $normalized = PayoutChannel::normalizeDetails(PayoutChannel::BKASH, [
            'account_name' => 'Should Drop',
            'mobile' => '01711-111111',
        ]);

        $this->assertSame(['mobile' => '01711111111'], $normalized);
        PayoutChannel::assertValid(PayoutChannel::BKASH, $normalized);

        $this->expectException(\InvalidArgumentException::class);
        PayoutChannel::assertValid(PayoutChannel::NAGAD, ['mobile' => '12345']);
    }
}
