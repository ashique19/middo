<?php

use App\Models\Nav;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $adminId = Role::query()->where('name', 'admin')->value('id');
        $accountsId = Role::query()->where('name', 'accounts')->value('id');

        if ($adminId) {
            Nav::query()->updateOrCreate(
                ['role_id' => $adminId, 'route_name' => 'admin.bank-accounts.index'],
                ['title' => 'Bank accounts', 'order' => 46]
            );
            Nav::query()->updateOrCreate(
                ['role_id' => $adminId, 'route_name' => 'admin.bank-ledger'],
                ['title' => 'Bank ledger', 'order' => 47]
            );
        }

        if ($accountsId) {
            Nav::query()->updateOrCreate(
                ['role_id' => $accountsId, 'route_name' => 'accounts.bank-ledger'],
                ['title' => 'Bank ledger', 'order' => 3]
            );
        }
    }

    public function down(): void
    {
        Nav::query()->whereIn('route_name', [
            'admin.bank-accounts.index',
            'admin.bank-ledger',
            'accounts.bank-ledger',
        ])->delete();
    }
};
