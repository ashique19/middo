<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `package_subscription_events` MODIFY `summary` TEXT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE package_subscription_events ALTER COLUMN summary TYPE TEXT');

            return;
        }

        // SQLite: recreate is unnecessary for tests; string already accepts long values.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `package_subscription_events` MODIFY `summary` VARCHAR(255) NULL');
        }
    }
};
