<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->integer('price')->default(0);
            $table->string('thumbnail')->nullable();

            $table->integer('kitchen_commission')->default(0);
            
            // Flags for display logic
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_homepage')->default(false);
            
            // Sorting
            $table->integer('display_order')->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};