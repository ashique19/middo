<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_subscription_id')->constrained('package_subscriptions')->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('summary')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['package_subscription_id', 'created_at']);
            $table->index(['package_subscription_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_subscription_events');
    }
};
