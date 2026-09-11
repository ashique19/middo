<?php

use App\Support\StaffNavSync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Drop sidebar Alerts leaves; bell lives in the top bar / app header.
        StaffNavSync::syncAll();
    }

    public function down(): void
    {
        // Structure-driven sync; restoring Alerts requires reverting StaffNavStructure.
        StaffNavSync::syncAll();
    }
};
