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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            // Relational Foreign Keys
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_item_id')->constrained()->onDelete('cascade');
            
            // Logistics & Quantification Tracking
            $table->integer('quantity')->default(1);
            $table->date('delivery_date');      // Tracks specific calendar date selections (e.g. 2026-06-26)
            $table->string('delivery_time', 20); // Tracks delivery window choice strings (e.g. "12:00 PM")
            
            // Financial Line Audit - Changed to Integer for local currency (BDT) optimization
            $table->integer('total_amount'); // Calculated granularly per row item line ($dish->price * $qty)
            
            // Preserved Snapshot Address Metrics
            // Saving strings here protects historical orders if user changes profile data later
            $table->text('address'); 
            
            // Workflow status state
            $table->enum('status', ['pending', 'processing', 'delivered', 'cancelled', 'others'])
                ->default('pending');
            
            // Audit Log Identity Tracking Columns
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            // Native Laravel timestamps handles: 'created_at' and 'updated_at' tracking fields automatically
            $table->timestamps();

            // Index optimized filters for corporate dashboard scheduling query paths
            $table->index(['user_id', 'delivery_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};