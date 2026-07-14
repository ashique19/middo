<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->integer('meals_cost')->default(0)->after('display_order');
            $table->integer('other_cost')->default(0)->after('meals_cost');
            $table->text('note')->nullable()->after('other_cost');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['meals_cost', 'other_cost', 'note']);
        });
    }
};
