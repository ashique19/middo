<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('setting_change_logs')) {
            return;
        }

        Schema::create('setting_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 64)->default('admin.settings');
            $table->string('summary', 500)->nullable();
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_change_logs');
    }
};
