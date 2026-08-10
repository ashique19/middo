<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot independent delivery charge (post delivery-coupon) and inclusive
 * delivery VAT separately from food VAT / other charges.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'delivery_charge_amount')) {
                    $table->unsignedInteger('delivery_charge_amount')->default(0)->after('charges_amount');
                }
                if (! Schema::hasColumn('orders', 'other_charges_amount')) {
                    $table->unsignedInteger('other_charges_amount')->default(0)->after('delivery_charge_amount');
                }
                if (! Schema::hasColumn('orders', 'delivery_discount_amount')) {
                    $table->unsignedInteger('delivery_discount_amount')->default(0)->after('other_charges_amount');
                }
                if (! Schema::hasColumn('orders', 'delivery_vat_rate_pct')) {
                    $table->decimal('delivery_vat_rate_pct', 5, 2)->default(0)->after('delivery_discount_amount');
                }
                if (! Schema::hasColumn('orders', 'delivery_vat_amount')) {
                    $table->unsignedInteger('delivery_vat_amount')->default(0)->after('delivery_vat_rate_pct');
                }
            });
        }

        if (Schema::hasTable('package_subscriptions')) {
            Schema::table('package_subscriptions', function (Blueprint $table) {
                if (! Schema::hasColumn('package_subscriptions', 'delivery_charge_amount')) {
                    $table->unsignedInteger('delivery_charge_amount')->default(0)->after('charges_amount');
                }
                if (! Schema::hasColumn('package_subscriptions', 'other_charges_amount')) {
                    $table->unsignedInteger('other_charges_amount')->default(0)->after('delivery_charge_amount');
                }
                if (! Schema::hasColumn('package_subscriptions', 'delivery_discount_amount')) {
                    $table->unsignedInteger('delivery_discount_amount')->default(0)->after('other_charges_amount');
                }
                if (! Schema::hasColumn('package_subscriptions', 'delivery_vat_rate_pct')) {
                    $table->decimal('delivery_vat_rate_pct', 5, 2)->default(0)->after('delivery_discount_amount');
                }
                if (! Schema::hasColumn('package_subscriptions', 'delivery_vat_amount')) {
                    $table->unsignedInteger('delivery_vat_amount')->default(0)->after('delivery_vat_rate_pct');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach ([
                    'delivery_vat_amount',
                    'delivery_vat_rate_pct',
                    'delivery_discount_amount',
                    'other_charges_amount',
                    'delivery_charge_amount',
                ] as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('package_subscriptions')) {
            Schema::table('package_subscriptions', function (Blueprint $table) {
                foreach ([
                    'delivery_vat_amount',
                    'delivery_vat_rate_pct',
                    'delivery_discount_amount',
                    'other_charges_amount',
                    'delivery_charge_amount',
                ] as $col) {
                    if (Schema::hasColumn('package_subscriptions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
