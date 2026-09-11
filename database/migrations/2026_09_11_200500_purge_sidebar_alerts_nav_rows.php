<?php

use App\Support\StaffNavSync;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensure Alerts rows are gone even if the earlier sync migration already ran
 * before purgeAlertLeaves existed (deployed DBs that still show sidebar Alerts).
 */
return new class extends Migration
{
    public function up(): void
    {
        StaffNavSync::purgeAlertLeaves();
    }

    public function down(): void
    {
        // no-op: Alerts belong in the top-bar bell, not the sidebar
    }
};
