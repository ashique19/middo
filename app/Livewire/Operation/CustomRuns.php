<?php

namespace App\Livewire\Operation;

use App\Models\Area;
use App\Models\CustomRun;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoOperatingCosts;
use App\Support\MiddoSettings;
use App\Support\RiderCommission;
use App\Support\StaffAlerts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class CustomRuns extends Component
{
    use WithPagination;

    public string $fromLabel = '';

    public string $toLabel = '';

    public ?int $areaId = null;

    public ?int $riderUserId = null;

    public $commissionAmount = null;

    public string $notes = '';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);
        $this->commissionAmount = MiddoSettings::deliveryCommissionDefault(DeliveryRunType::CUSTOM);
    }

    public function updatedRiderUserId($value): void
    {
        if (! $value) {
            return;
        }
        $rider = User::query()->find((int) $value);
        if ($rider) {
            $this->commissionAmount = RiderCommission::forSettingsRun($rider, DeliveryRunType::CUSTOM);
        }
    }

    public function createRun(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $this->validate([
                'fromLabel' => 'required|string|max:120',
                'toLabel' => 'required|string|max:120',
                'areaId' => 'nullable|exists:areas,id',
                'riderUserId' => 'nullable|exists:users,id',
                'commissionAmount' => 'required|integer|min:0|max:100000',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($this->riderUserId) {
                $rider = User::query()->with('role')->findOrFail((int) $this->riderUserId);
                if ($rider->role?->name !== 'delivery') {
                    throw new \RuntimeException('Assignee must be a delivery rider.');
                }
                if ($this->areaId && ! $rider->servesArea((int) $this->areaId)) {
                    throw new \RuntimeException('Selected rider does not serve that area.');
                }
            }

            $run = CustomRun::create([
                'from_label' => trim($this->fromLabel),
                'to_label' => trim($this->toLabel),
                'area_id' => $this->areaId,
                'rider_user_id' => $this->riderUserId,
                'commission_amount' => (int) $this->commissionAmount,
                'status' => CustomRun::STATUS_PENDING,
                'notes' => $this->notes ?: null,
                'created_by' => Auth::id(),
            ]);

            StaffAlerts::notifyRidersCustomRun($run);

            $this->reset(['fromLabel', 'toLabel', 'areaId', 'riderUserId', 'notes']);
            $this->commissionAmount = MiddoSettings::deliveryCommissionDefault(DeliveryRunType::CUSTOM);
            $this->statusMessage = "Custom run #{$run->id} created ({$run->label()}).";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not create custom run.';
        }
    }

    public function cancelRun(int $id): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
        $actorId = (int) Auth::id();

        try {
            DB::transaction(function () use ($id, $actorId) {
                $run = CustomRun::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                if ($run->isCompleted() || $run->isCancelled()) {
                    throw new \RuntimeException('This custom run is already finished.');
                }
                if (! $run->isPending() && ! $run->isStarted()) {
                    throw new \RuntimeException('Only pending or started runs can be cancelled.');
                }

                if ($run->isStarted()) {
                    MiddoOperatingCosts::voidRiderCommission(
                        DeliveryRunType::CUSTOM,
                        CustomRun::class,
                        (int) $run->id,
                        $actorId,
                        'Ops cancelled started custom run #'.$run->id
                    );
                }

                $run->update([
                    'status' => CustomRun::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);
            });

            $this->statusMessage = "Custom run #{$id} cancelled.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not cancel run.';
        }
    }

    public function reassignRun(int $id, ?int $riderUserId = null): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $run = CustomRun::query()->findOrFail($id);
            if (! $run->isPending()) {
                throw new \RuntimeException('Only pending runs can be reassigned. Cancel a started run first.');
            }

            if ($riderUserId) {
                $rider = User::query()->with('role')->findOrFail($riderUserId);
                if ($rider->role?->name !== 'delivery') {
                    throw new \RuntimeException('Assignee must be a delivery rider.');
                }
                if ($run->area_id && ! $rider->servesArea((int) $run->area_id)) {
                    throw new \RuntimeException('Selected rider does not serve that area.');
                }
            }

            $run->update(['rider_user_id' => $riderUserId]);
            $this->statusMessage = $riderUserId
                ? "Custom run #{$id} reassigned."
                : "Custom run #{$id} returned to open pool.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not reassign run.';
        }
    }

    public function render()
    {
        $runs = CustomRun::query()
            ->with(['rider', 'area', 'creator'])
            ->latest('id')
            ->paginate(20);

        $areas = Area::query()->orderBy('name')->get(['id', 'name']);
        $riders = User::query()
            ->with(['role', 'areas'])
            ->whereHas('role', fn ($q) => $q->where('name', 'delivery'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();

        $role = Auth::user()?->role?->name;
        $layout = 'layouts.private.app';

        return view('livewire.operation.custom-runs', [
            'runs' => $runs,
            'areas' => $areas,
            'riders' => $riders,
        ])->layout($layout, ['title' => 'Custom runs']);
    }
}
