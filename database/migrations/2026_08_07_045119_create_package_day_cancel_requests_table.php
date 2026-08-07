<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_day_cancel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_subscription_id')->constrained('package_subscriptions')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->date('delivery_date');
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->text('ops_note')->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('refunded_amount')->nullable();
            $table->timestamps();

            $table->index(['package_subscription_id', 'status']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_day_cancel_requests');
    }
};
