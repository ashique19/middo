<?php

namespace App\Livewire\Operation;

use App\Models\CustomRun;
use App\Models\Order;
use App\Models\User;
use App\Support\DeliveryRunType;
use App\Support\MiddoOperatingCosts;
use App\Support\OpsRiderBoard;
use App\Support\OpsRiderMidRunReassign;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RidersBoard extends Component
{
    public string $tab = 'riders';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public ?int $reassignRunId = null;

    public ?int $reassignRiderId = null;

    public ?int $reassignOrderId = null;

    public ?int $reassignOrderRiderId = null;

    public string $reassignOrderReason = '';

    public function mount(): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function openReassign(int $runId): void
    {
        $this->errorMessage = '';
        $this->cancelOrderReassign();
        $this->reassignRunId = $runId;
        $this->reassignRiderId = CustomRun::query()->whereKey($runId)->value('rider_user_id');
    }

    public function cancelReassign(): void
    {
        $this->reassignRunId = null;
        $this->reassignRiderId = null;
    }

    public function openOrderReassign(int $orderId): void
    {
        $this->errorMessage = '';
        $this->cancelReassign();
        $this->reassignOrderId = $orderId;
        $this->reassignOrderRiderId = Order::query()->whereKey($orderId)->value('delivery_rider_id');
        $this->reassignOrderReason = '';
    }

    public function cancelOrderReassign(): void
    {
        $this->reassignOrderId = null;
        $this->reassignOrderRiderId = null;
        $this->reassignOrderReason = '';
    }

    public function confirmOrderReassign(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            if (! $this->reassignOrderId || ! $this->reassignOrderRiderId) {
                throw new \RuntimeException('Select a rescue rider.');
            }

            $order = Order::query()->findOrFail($this->reassignOrderId);
            $rider = User::query()->with('role')->findOrFail((int) $this->reassignOrderRiderId);

            OpsRiderMidRunReassign::reassign(
                $order,
                $rider,
                Auth::user(),
                $this->reassignOrderReason !== '' ? $this->reassignOrderReason : null
            );

            $this->statusMessage = "Order #{$order->id} reassigned to {$rider->name}. Starter keeps commission; Due does not peer-transfer.";
            $this->cancelOrderReassign();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not reassign order.';
        }
    }

    public function confirmReassign(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            if (! $this->reassignRunId) {
                return;
            }

            $run = CustomRun::query()->findOrFail($this->reassignRunId);
            if (! $run->isPending()) {
                throw new \RuntimeException('Only pending custom runs can be reassigned. Cancel a started run first.');
            }

            $riderId = $this->reassignRiderId ? (int) $this->reassignRiderId : null;
            if ($riderId) {
                $rider = User::query()->with('role')->findOrFail($riderId);
                if ($rider->role?->name !== 'delivery') {
                    throw new \RuntimeException('Assignee must be a delivery rider.');
                }
                if ($run->area_id && ! $rider->servesArea((int) $run->area_id)) {
                    throw new \RuntimeException('Selected rider does not serve that area.');
                }
            }

            $run->update(['rider_user_id' => $riderId]);
            $this->statusMessage = $riderId
                ? "Custom run #{$run->id} assigned to {$rider->name}."
                : "Custom run #{$run->id} returned to open pool.";
            $this->cancelReassign();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not reassign run.';
        }
    }

    public function cancelCustomRun(int $runId): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
        $actorId = (int) Auth::id();

        try {
            DB::transaction(function () use ($runId, $actorId) {
                $run = CustomRun::query()->whereKey($runId)->lockForUpdate()->first();
                if (! $run) {
                    throw new \RuntimeException('Custom run not found.');
                }
                if ($run->isCompleted() || $run->isCancelled()) {
                    throw new \RuntimeException('This custom run is already finished.');
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

            $this->statusMessage = "Custom run #{$runId} cancelled.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not cancel custom run.';
        }
    }

    public function render()
    {
        $counts = OpsRiderBoard::counts();
        $ridersList = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'delivery'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'mobile', 'rider_shift_status']);

        return view('livewire.operation.riders-board', [
            'counts' => $counts,
            'riders' => OpsRiderBoard::riders(),
            'awaiting' => OpsRiderBoard::awaitingAccept(),
            'onTheWay' => OpsRiderBoard::onTheWay(),
            'boxes' => OpsRiderBoard::boxCustody(),
            'customRuns' => OpsRiderBoard::customRunsActive(),
            'riderOptions' => $ridersList,
            'rolePrefix' => $this->rolePrefix(),
        ])->layout('layouts.private.app', ['title' => 'Rider ops']);
    }
}
