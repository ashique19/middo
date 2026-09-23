<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('kitchen_rating')->nullable()->after('profile_photo_path');
            $table->text('kitchen_rating_note')->nullable()->after('kitchen_rating');
        });

        Schema::create('kitchen_rating_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('old_rating')->nullable();
            $table->unsignedTinyInteger('new_rating')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['kitchen_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_rating_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kitchen_rating', 'kitchen_rating_note']);
        });
    }
};
