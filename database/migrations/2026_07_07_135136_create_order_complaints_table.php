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
        Schema::create('order_complaints', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('order_complaints')->cascadeOnDelete();

            $table->boolean('is_reply')->default(false);

            $table->enum('category', ['delivery', 'food_quality', 'payment', 'other'])->nullable();

            $table->text('message');
            $table->string('attachment')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['order_id', 'parent_id']);
            $table->index('is_reply');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_complaints');
    }
};
