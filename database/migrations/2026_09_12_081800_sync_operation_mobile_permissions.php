<?php

use App\Support\OperationPermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        OperationPermissions::syncOperationRole();
    }

    public function down(): void
    {
        // Permissions remain; detaching would break seeded ops users mid-flight.
    }
};
