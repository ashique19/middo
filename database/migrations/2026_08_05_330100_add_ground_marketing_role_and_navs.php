<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Role `ground_marketing` and marketing navs are created by
        // RolePermissionSeeder / NavSeeder. Avoid inserting here: Role::$fillable
        // excludes `id`, which previously stole auto-increment id=1 and broke seeding.
    }

    public function down(): void
    {
        // Intentionally empty — role/nav cleanup is handled by seeders on refresh.
    }
};
