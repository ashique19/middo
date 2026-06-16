<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create Users Table
        // 1. Create Users Table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name')->virtualAs("concat(first_name, ' ', last_name)");
            $table->string('email')->nullable()->unique();
            $table->string('mobile', 20)->unique()->index(); 
            $table->string('alt_mobile', 20)->nullable();
            $table->string('password');
            $table->string('otp', 10)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->text('address')->nullable();
            $table->string('area')->nullable();
            $table->string('city')->nullable();
            
            // Add this foreign key for RBAC
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null');

            $table->enum('status', ['inactive', 'active'])->default('inactive');
            $table->boolean('is_mobile_verified')->default(0);

            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();

            $table->rememberToken();
            $table->timestamps();
        });

        // 2. Password Resets (Standardized to 'mobile')
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('mobile')->primary(); 
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // 3. Sessions (Standard Laravel structure)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};