<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->string('thumbnail')->nullable();
            $table->integer('recipe_ingredient_cost')->default(0);
            $table->integer('other_costs')->default(0);
            $table->integer('total_cost')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_item_meal_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('meal_item_id')->constrained('meal_items')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['menu_item_id', 'meal_item_id']);
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_item_id')->constrained('meal_items')->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('training_video_url')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['meal_item_id', 'is_active']);
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 10, 3)->default(0);
            $table->string('unit')->nullable();
            $table->integer('cost')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('recipe_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->string('path');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_photos');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('menu_item_meal_item');
        Schema::dropIfExists('meal_items');
    }
};
