<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedInteger('delivery_commission')->default(0)->after('kitchen_commission');
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'food_amount')) {
                $table->unsignedInteger('food_amount')->default(0)->after('total_amount');
            }
            if (! Schema::hasColumn('orders', 'charges_amount')) {
                $after = Schema::hasColumn('orders', 'food_amount') ? 'food_amount' : 'total_amount';
                $table->unsignedInteger('charges_amount')->default(0)->after($after);
            }
            if (! Schema::hasColumn('orders', 'discount_amount')) {
                $table->unsignedInteger('discount_amount')->default(0)->after('charges_amount');
            }
            if (! Schema::hasColumn('orders', 'kitchen_share_amount')) {
                $table->unsignedInteger('kitchen_share_amount')->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('orders', 'delivery_share_amount')) {
                $table->unsignedInteger('delivery_share_amount')->default(0)->after('kitchen_share_amount');
            }
            if (! Schema::hasColumn('orders', 'direct_cost_amount')) {
                $table->unsignedInteger('direct_cost_amount')->default(0)->after('delivery_share_amount');
            }
            if (! Schema::hasColumn('orders', 'middo_rest_amount')) {
                $table->unsignedInteger('middo_rest_amount')->default(0)->after('direct_cost_amount');
            }
        });

        Schema::create('order_money_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('bucket', 40);
            $table->integer('amount'); // signed BDT
            $table->unsignedInteger('middo_cash_balance_after')->nullable();
            $table->string('channel', 40)->nullable(); // wallet|gateway|cash|accrual|settlement|system
            $table->nullableMorphs('reference');
            $table->json('meta')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['event_type', 'bucket']);
        });

        Schema::create('partner_payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('beneficiary_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('beneficiary_role', 20); // kitchen|delivery
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('open'); // open|settled|void
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('middo_cash_ledger_entry_id')->nullable()->constrained('middo_cash_ledger')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'beneficiary_role']);
            $table->unique(['order_id', 'beneficiary_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payables');
        Schema::dropIfExists('order_money_events');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'food_amount',
                'charges_amount',
                'discount_amount',
                'kitchen_share_amount',
                'delivery_share_amount',
                'direct_cost_amount',
                'middo_rest_amount',
            ]);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('delivery_commission');
        });
    }
};
