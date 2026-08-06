<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `package_subscription_events` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        DB::statement('ALTER TABLE `package_subscription_events` MODIFY `summary` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
        DB::statement('ALTER TABLE `package_subscription_events` MODIFY `type` VARCHAR(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
    }

    public function down(): void
    {
        // Intentionally empty — do not downgrade charset.
    }
};
