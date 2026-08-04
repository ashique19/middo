<?php

namespace App\Support;

use App\Models\MiddoOperatingCost;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Book non-order rider commissions (box / custom) into Middo P&L cost + rider wallet.
 */
class MiddoOperatingCosts
{
    public static function bookRiderCommission(
        User $rider,
        string $runType,
        int $amount,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $createdBy = null,
    ): ?MiddoOperatingCost {
        if ($amount < 1 || ! Schema::hasTable('middo_operating_costs')) {
            return null;
        }

        if ($referenceType && $referenceId) {
            $existing = MiddoOperatingCost::query()
                ->where('cost_type', MiddoOperatingCost::TYPE_RIDER_COMMISSION)
                ->where('run_type', $runType)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->first();
            if ($existing) {
                return null;
            }
        }

        $cost = MiddoOperatingCost::query()->create([
            'cost_type' => MiddoOperatingCost::TYPE_RIDER_COMMISSION,
            'amount' => $amount,
            'run_type' => $runType,
            'rider_user_id' => $rider->id,
            'order_id' => null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description ?? (DeliveryRunType::label($runType).' commission'),
            'created_by' => $createdBy ?? $rider->id,
        ]);

        if (Schema::hasTable('rider_account_ledger')) {
            RiderAccountLedger::credit(
                (int) $rider->id,
                $amount,
                'commission_accrued',
                MiddoOperatingCost::class,
                $cost->id,
                $cost->description,
                $createdBy ?? $rider->id
            );
        }

        return $cost;
    }
}
