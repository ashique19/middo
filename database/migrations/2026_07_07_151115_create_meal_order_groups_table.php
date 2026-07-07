<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_order_groups', function (Blueprint $table) {
            $table->id();
            $table->string('unique_group_name');
            $table->foreignId('menu_id')->constrained('menu_items')->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('kitchen_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique('order_id');
            $table->index('unique_group_name');
            $table->index(['menu_id', 'unique_group_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_order_groups');
    }
};
