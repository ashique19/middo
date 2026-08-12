<?php

namespace Tests\Feature;

use App\Support\UserAuditPresenter;
use Tests\TestCase;

class UserAuditPresenterTest extends TestCase
{
    public function test_formats_changes_and_login_metadata_for_humans(): void
    {
        $lines = UserAuditPresenter::metadataLines([
            'changes' => [
                'status' => ['from' => 'active', 'to' => 'inactive'],
                'password' => ['from' => '[redacted]', 'to' => '[changed]'],
            ],
            'reason' => 'inactive',
            'device_name' => 'Nabila\'s phone',
            'remember' => true,
        ]);

        $this->assertContains('Status changed from active to inactive', $lines);
        $this->assertContains('Password changed from hidden to new password', $lines);
        $this->assertContains('Reason: Account not active', $lines);
        $this->assertContains('Device: Nabila\'s phone', $lines);
        $this->assertContains('Stay signed in: Yes', $lines);
    }

    public function test_source_labels_are_plain_language(): void
    {
        $this->assertSame('Corporate app', UserAuditPresenter::sourceLabel('corporate_mobile'));
        $this->assertSame('Admin panel', UserAuditPresenter::sourceLabel('admin'));
        $this->assertSame('Website', UserAuditPresenter::sourceLabel('web'));
    }
}
