<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('middo_boxes', function (Blueprint $table) {
            $table->foreignId('kitchen_id')
                ->nullable()
                ->after('held_by_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('kitchen_id');
        });
    }

    public function down(): void
    {
        Schema::table('middo_boxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kitchen_id');
        });
    }
};
