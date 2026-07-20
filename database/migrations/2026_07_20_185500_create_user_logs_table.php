<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('event', 50);
            $table->string('source', 32);

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('event');
            $table->index('source');
            $table->index('performed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};
