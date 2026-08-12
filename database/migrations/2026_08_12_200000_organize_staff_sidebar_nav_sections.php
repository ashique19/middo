<?php

use App\Support\StaffNavSync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        StaffNavSync::syncAll();
    }

    public function down(): void
    {
        // Irreversible organization; re-run older nav migrations / NavSeeder to rebuild.
    }
};
