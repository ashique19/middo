<?php

namespace App\Livewire\Delivery;

use App\Models\CustomRun;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoOperatingCosts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class CustomRuns extends Component
{
    use WithPagination;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function startRun(int $runId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $riderId = (int) Auth::id();

        try {
            DB::transaction(function () use ($runId, $riderId) {
                $run = CustomRun::query()->whereKey($runId)->lockForUpdate()->first();
                if (! $run || ! $run->isPending()) {
                    throw new \RuntimeException('This custom run is no longer available to start.');
                }

                $rider = User::query()->findOrFail($riderId);

                if ($run->rider_user_id !== null && (int) $run->rider_user_id !== $riderId) {
                    throw new \RuntimeException('This run is assigned to another rider.');
                }

                if ($run->rider_user_id === null) {
                    if ($run->area_id !== null && ! $rider->servesArea((int) $run->area_id)) {
                        throw new \RuntimeException('This run is outside your service areas.');
                    }
                }

                $run->update([
                    'rider_user_id' => $riderId,
                    'status' => CustomRun::STATUS_STARTED,
                    'started_at' => now(),
                ]);

                $amount = (int) $run->commission_amount;
                if ($amount > 0) {
                    MiddoOperatingCosts::bookRiderCommission(
                        $rider,
                        DeliveryRunType::CUSTOM,
                        $amount,
                        CustomRun::class,
                        (int) $run->id,
                        'Custom run #'.$run->id.': '.$run->label(),
                        $riderId
                    );
                }
            });

            $this->statusMessage = "Started custom run #{$runId}.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not start custom run.';
        }
    }

    public function completeRun(int $runId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
        $riderId = (int) Auth::id();

        try {
            DB::transaction(function () use ($runId, $riderId) {
                $run = CustomRun::query()->whereKey($runId)->lockForUpdate()->first();
                if (! $run || ! $run->isStarted()) {
                    throw new \RuntimeException('This custom run is not in progress.');
                }
                if ((int) $run->rider_user_id !== $riderId) {
                    throw new \RuntimeException('Only the assigned rider can complete this run.');
                }

                $run->update([
                    'status' => CustomRun::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            });

            $this->statusMessage = "Completed custom run #{$runId}.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not complete custom run.';
        }
    }

    public function render()
    {
        $rider = Auth::user();

        $runs = CustomRun::query()
            ->with(['area', 'rider'])
            ->visibleToRider($rider)
            ->whereIn('status', [CustomRun::STATUS_PENDING, CustomRun::STATUS_STARTED])
            ->orderByRaw("CASE WHEN status = 'started' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.delivery.custom-runs', [
            'runs' => $runs,
        ])->layout('delivery.layout.app', ['title' => 'Custom runs']);
    }
}
