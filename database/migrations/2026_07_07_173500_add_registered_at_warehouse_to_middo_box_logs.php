<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: registered_at_warehouse is defined in create_middo_box_logs_table.
    }

    public function down(): void
    {
        // No-op: avoids enum rollback failures when rows use registered_at_warehouse.
    }
};
