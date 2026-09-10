<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'pod_photo_path')) {
                $table->string('pod_photo_path')->nullable()->after('dispatched_at');
            }
            if (! Schema::hasColumn('orders', 'pod_verified_at')) {
                $table->timestamp('pod_verified_at')->nullable()->after('pod_photo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'pod_verified_at')) {
                $table->dropColumn('pod_verified_at');
            }
            if (Schema::hasColumn('orders', 'pod_photo_path')) {
                $table->dropColumn('pod_photo_path');
            }
        });
    }
};
