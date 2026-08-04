<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_runs', function (Blueprint $table) {
            $table->id();
            $table->string('from_label');
            $table->string('to_label');
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('rider_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('commission_amount')->default(0);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'area_id']);
            $table->index(['rider_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_runs');
    }
};
