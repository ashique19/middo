<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $adminId = Role::query()->where('name', 'admin')->value('id');
        if (! $adminId) {
            return;
        }

        Nav::query()->updateOrCreate(
            ['role_id' => $adminId, 'route_name' => 'admin.payout-banks.index'],
            ['title' => 'Payout banks', 'order' => 46]
        );

        // Keep Middo float accounts just after the catalog.
        Nav::query()
            ->where('role_id', $adminId)
            ->where('route_name', 'admin.bank-accounts.index')
            ->update(['order' => 47]);

        Nav::query()
            ->where('role_id', $adminId)
            ->where('route_name', 'admin.bank-ledger')
            ->update(['order' => 48]);
    }

    public function down(): void
    {
        Nav::query()->where('route_name', 'admin.payout-banks.index')->delete();
    }
};
