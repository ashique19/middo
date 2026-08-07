<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_box_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'kitchen_id']);
            $table->index(['kitchen_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_box_requests');
    }
};
