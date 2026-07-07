<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('menu_id')->constrained('menu_items')->onDelete('cascade');
            $table->date('delivery_date');
            $table->foreignId('kitchen_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['delivery_date', 'menu_id']);
            $table->index('name');
        });

        Schema::create('order_group_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique('order_id');
        });

        if (Schema::hasTable('meal_order_groups')) {
            $this->migrateLegacyMealOrderGroups();
            Schema::dropIfExists('meal_order_groups');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_group_orders');
        Schema::dropIfExists('order_groups');

        Schema::create('meal_order_groups', function (Blueprint $table) {
            $table->id();
            $table->string('unique_group_name');
            $table->foreignId('menu_id')->constrained('menu_items')->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('kitchen_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique('order_id');
            $table->index('unique_group_name');
            $table->index(['menu_id', 'unique_group_name']);
        });
    }

    protected function migrateLegacyMealOrderGroups(): void
    {
        $legacyRows = DB::table('meal_order_groups')->orderBy('id')->get();

        foreach ($legacyRows->groupBy('unique_group_name') as $groupName => $rows) {
            $first = $rows->first();
            $deliveryDate = DB::table('orders')
                ->where('id', $first->order_id)
                ->value('delivery_date');

            $orderGroupId = DB::table('order_groups')->insertGetId([
                'name' => $groupName,
                'menu_id' => $first->menu_id,
                'delivery_date' => $deliveryDate,
                'kitchen_id' => $first->kitchen_id,
                'created_by' => $first->created_by,
                'updated_by' => $first->updated_by,
                'created_at' => $first->created_at,
                'updated_at' => $first->updated_at,
            ]);

            foreach ($rows as $row) {
                DB::table('order_group_orders')->insert([
                    'order_group_id' => $orderGroupId,
                    'order_id' => $row->order_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }
    }
};
