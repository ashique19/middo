<?php

namespace App\Livewire\Operation;

use App\Models\OrderGroup;
use App\Models\User;
use App\Support\KitchenCapacity;
use App\Support\OpsSlaBoard;
use App\Support\OrderKitchenAcceptance;
use App\Support\StaffAlerts;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class SlaBoard extends Component
{
    public string $tab = 'unassigned';

    /** @var int[] */
    public array $selectedGroupIds = [];

    public ?int $bulkKitchenId = null;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $role = Auth::user()?->role?->name;
        abort_unless(in_array($role, ['admin', 'operation'], true), 403);
    }

    #[On('order-group-kitchen-changed')]
    public function refreshBoard(): void
    {
        $this->selectedGroupIds = [];
        $this->statusMessage = null;
    }

    public function toggleGroup(int $groupId): void
    {
        if (in_array($groupId, $this->selectedGroupIds, true)) {
            $this->selectedGroupIds = array_values(array_filter(
                $this->selectedGroupIds,
                fn (int $id) => $id !== $groupId
            ));
        } else {
            $this->selectedGroupIds[] = $groupId;
        }
    }

    public function bulkAssignKitchen(): void
    {
        if (! $this->bulkKitchenId || $this->selectedGroupIds === []) {
            $this->statusMessage = 'Select groups and a kitchen first.';

            return;
        }

        $kitchen = User::query()
            ->whereKey($this->bulkKitchenId)
            ->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))
            ->where('status', 'active')
            ->first();

        if (! $kitchen) {
            $this->statusMessage = 'Kitchen not found.';

            return;
        }

        $assigned = 0;
        foreach ($this->selectedGroupIds as $groupId) {
            $group = OrderGroup::with('orders')->find($groupId);
            if (! $group || $group->kitchen_id !== null) {
                continue;
            }

            try {
                KitchenCapacity::assertCanAccept($kitchen);
            } catch (\RuntimeException $e) {
                $this->statusMessage = "Assigned {$assigned} group(s). Stopped: ".$e->getMessage();
                $this->selectedGroupIds = [];
                $this->bulkKitchenId = null;

                return;
            }

            $group->update([
                'kitchen_id' => $kitchen->id,
                'updated_by' => Auth::id(),
            ]);
            OrderKitchenAcceptance::markGroupOrdersProcessing($group, Auth::id());
            StaffAlerts::notifyKitchenAssigned($group->fresh(['menuItem']), $kitchen);
            $assigned++;
        }

        $this->selectedGroupIds = [];
        $this->bulkKitchenId = null;
        $this->statusMessage = $assigned > 0
            ? "Assigned {$assigned} group(s) to {$kitchen->name}."
            : 'No groups were assigned.';
        $this->dispatch('order-group-kitchen-changed');
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function render()
    {
        $counts = OpsSlaBoard::counts();
        $unassigned = OpsSlaBoard::unassignedGroups();
        $late = OpsSlaBoard::lateToPack();
        $kitchenOptions = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'remaining' => KitchenCapacity::remainingSlots($u),
            ])
            ->all();

        return view('livewire.operation.sla-board', [
            'counts' => $counts,
            'unassigned' => $unassigned,
            'late' => $late,
            'rolePrefix' => $this->rolePrefix(),
            'kitchenOptions' => $kitchenOptions,
            'kitchenHints' => OpsSlaBoard::kitchenCapacityHints(),
        ])->layout('layouts.private.app', ['title' => 'Dispatch SLA']);
    }
}
