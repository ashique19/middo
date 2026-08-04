<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'area_id']);
            $table->index('area_id');
        });

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'rider_commission_overrides')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('rider_commission_overrides')->nullable()->after('area_id');
            });
        }

        $deliveryRoleId = Role::query()->where('name', 'delivery')->value('id');
        if ($deliveryRoleId) {
            $riders = User::query()
                ->where('role_id', $deliveryRoleId)
                ->whereNotNull('area_id')
                ->get(['id', 'area_id']);

            $now = now();
            foreach ($riders as $rider) {
                DB::table('area_user')->insertOrIgnore([
                    'user_id' => $rider->id,
                    'area_id' => $rider->area_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('area_user');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'rider_commission_overrides')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('rider_commission_overrides');
            });
        }
    }
};
