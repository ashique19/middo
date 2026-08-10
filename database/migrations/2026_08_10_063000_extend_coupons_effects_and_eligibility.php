<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends coupons with waive_charge effect + eligibility scopes
 * (menus, areas, companies, first-order, min quantity).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'waive_charge_category')) {
                $table->string('waive_charge_category', 40)->nullable()->after('type');
            }
            if (! Schema::hasColumn('coupons', 'waive_charge_id')) {
                $table->foreignId('waive_charge_id')
                    ->nullable()
                    ->after('waive_charge_category')
                    ->constrained('charges')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('coupons', 'eligibility')) {
                $table->json('eligibility')->nullable()->after('applies_to');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'waive_charge_id')) {
                $table->dropConstrainedForeignId('waive_charge_id');
            }
            if (Schema::hasColumn('coupons', 'waive_charge_category')) {
                $table->dropColumn('waive_charge_category');
            }
            if (Schema::hasColumn('coupons', 'eligibility')) {
                $table->dropColumn('eligibility');
            }
        });
    }
};
