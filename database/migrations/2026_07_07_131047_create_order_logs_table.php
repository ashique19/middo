<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event', 50);

            $table->json('metadata')->nullable();

            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index('event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_logs');
    }
};
