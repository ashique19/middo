<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bd_banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bd_bank_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bd_bank_id')->constrained('bd_banks')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['bd_bank_id', 'name']);
        });

        Schema::create('bd_bank_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bd_bank_city_id')->constrained('bd_bank_cities')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['bd_bank_city_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bd_bank_branches');
        Schema::dropIfExists('bd_bank_cities');
        Schema::dropIfExists('bd_banks');
    }
};
