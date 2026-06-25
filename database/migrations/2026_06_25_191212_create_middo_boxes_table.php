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
        Schema::create('middo_boxes', function (Blueprint $table) {
            $table->id();
            
            // Core Identity Tokens
            $table->string('qr_code_id')->unique(); 
            $table->string('box_model_type')->default('standard_insulated'); 
            
            // Real-Time Custody Tracking (Points to the User currently holding the asset)
            $table->foreignId('held_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Asset Lifecycle State
            $table->enum('asset_status', ['at_middo_warehouse','active', 'maintenance', 'damaged', 'lost', 'retired'])->default('at_middo_warehouse');
            
            // Structural tracking columns
            $table->integer('total_uses_count')->default(0); 
            $table->timestamp('last_scanned_at')->nullable();
            
            $table->timestamps();

            // Indexes for rapid lookups
            $table->index('qr_code_id');
            $table->index('held_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('middo_boxes');
    }
};