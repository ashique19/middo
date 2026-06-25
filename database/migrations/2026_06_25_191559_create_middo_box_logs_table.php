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
        Schema::create('middo_box_logs', function (Blueprint $table) {
            $table->id();
            
            // Relational Foreign Key Mappings (order_id is optional for internal logistics transits)
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('middo_box_id')->constrained('middo_boxes')->onDelete('restrict'); 
            
            // Custody & Return Lifecycle State tracking
            $table->enum('custody_status', [
                'warehouse',
                'assigned_at_kitchen', 
                'in_transit',          
                'with_customer',       
                'collected_by_rider',  
                'returned_and_washed'  
            ])->default('warehouse');

            // Detailed Scan Milestones for operational transitions
            $table->enum('log_action', [
                'dispatched_to_kitchen',           // Warehouse -> Kitchen transit
                'received_at_kitchen',             // Arrived at Kitchen inventory pool
                'picked_by_delivery_from_kitchen', // Rider scanned out from kitchen
                'delivered_to_corporate',          // Dropped off at company desk
                'picked_from_corporate_by_delivery',// Rider retrieved empty container from client hub
                'returned_to_kitchen',             // Returned to kitchen for sorting/washing
                'returned_to_warehouse'            // Sent back to main storage node
            ]);
            
            $table->timestamps();

            // Compound indexing optimized for route tracking and asset history searches
            $table->index(['order_id', 'custody_status']);
            $table->index('middo_box_id');
            $table->index('log_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('middo_box_logs');
    }
};