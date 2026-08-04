<?php

use App\Models\Nav;
use App\Models\PartnerPayable;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_account_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('entry_type', 40);
            $table->nullableMorphs('reference');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kitchen_user_id', 'id']);
            $table->index(['entry_type']);
        });

        Schema::create('kitchen_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('kitchen_ledger_entry_id')->nullable()->constrained('kitchen_account_ledger')->nullOnDelete();
            $table->foreignId('middo_cash_ledger_entry_id')->nullable()->constrained('middo_cash_ledger')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'kitchen_user_id']);
        });

        Schema::create('kitchen_middo_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->string('proof_path')->nullable();
            $table->string('reference_code')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('middo_cash_ledger_entry_id')->nullable()->constrained('middo_cash_ledger')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'kitchen_user_id']);
        });

        // Backfill receivable ledger from open kitchen payables.
        if (Schema::hasTable('partner_payables')) {
            $open = PartnerPayable::query()
                ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
                ->where('status', PartnerPayable::STATUS_OPEN)
                ->whereNotNull('beneficiary_user_id')
                ->orderBy('id')
                ->get();

            $balances = [];
            $now = now();
            foreach ($open as $payable) {
                $kitchenId = (int) $payable->beneficiary_user_id;
                $balances[$kitchenId] = ($balances[$kitchenId] ?? 0) + (int) $payable->amount;
                DB::table('kitchen_account_ledger')->insert([
                    'kitchen_user_id' => $kitchenId,
                    'amount' => (int) $payable->amount,
                    'balance_after' => $balances[$kitchenId],
                    'entry_type' => 'share_accrued',
                    'reference_type' => PartnerPayable::class,
                    'reference_id' => $payable->id,
                    'description' => 'Backfill open kitchen share for order #'.$payable->order_id,
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->addNavs();
    }

    protected function addNavs(): void
    {
        $kitchenRoleId = Role::query()->where('name', 'kitchen')->value('id');
        if ($kitchenRoleId) {
            $exists = Nav::query()
                ->where('role_id', $kitchenRoleId)
                ->where('route_name', 'kitchen.account')
                ->exists();
            if (! $exists) {
                $max = (int) Nav::query()->where('role_id', $kitchenRoleId)->whereNull('parent_id')->max('order');
                Nav::create([
                    'title' => 'Account',
                    'route_name' => 'kitchen.account',
                    'order' => $max + 1,
                    'role_id' => $kitchenRoleId,
                ]);
            }
        }

        foreach (['admin', 'operation'] as $roleName) {
            $roleId = Role::query()->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            $route = $roleName === 'admin' ? 'admin.kitchen-money.index' : 'operation.kitchen-money.index';
            $exists = Nav::query()
                ->where('role_id', $roleId)
                ->where('route_name', $route)
                ->exists();
            if ($exists) {
                continue;
            }
            $max = (int) Nav::query()->where('role_id', $roleId)->whereNull('parent_id')->max('order');
            Nav::create([
                'title' => 'Kitchen money',
                'route_name' => $route,
                'icon' => '💸',
                'order' => $max + 1,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        Nav::query()->whereIn('route_name', [
            'kitchen.account',
            'admin.kitchen-money.index',
            'operation.kitchen-money.index',
        ])->delete();

        Schema::dropIfExists('kitchen_middo_transfers');
        Schema::dropIfExists('kitchen_withdrawal_requests');
        Schema::dropIfExists('kitchen_account_ledger');
    }
};
