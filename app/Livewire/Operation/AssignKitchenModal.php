<?php

namespace App\Livewire\Operation;

use App\Models\OrderGroup;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class AssignKitchenModal extends Component
{
    public bool $showModal = false;

    public ?int $orderGroupId = null;

    public string $groupName = '';

    public string $kitchenLabel = 'Unassigned';

    public ?int $selectedKitchenId = null;

    public array $kitchens = [];

    #[On('open-assign-kitchen-modal')]
    public function openModal($orderGroupId = null): void
    {
        $id = is_array($orderGroupId)
            ? ($orderGroupId['orderGroupId'] ?? null)
            : $orderGroupId;

        if (! $id) {
            return;
        }

        $group = OrderGroup::with('kitchen')->find((int) $id);

        if (! $group) {
            return;
        }

        $this->resetErrorBag();
        $this->orderGroupId = $group->id;
        $this->groupName = $group->name;
        $this->kitchenLabel = $group->kitchenDisplayName();
        $this->selectedKitchenId = $group->kitchen_id;
        $this->kitchens = $this->fetchKitchens();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->orderGroupId = null;
        $this->groupName = '';
        $this->kitchenLabel = 'Unassigned';
        $this->selectedKitchenId = null;
    }

    public function save(): void
    {
        if (! $this->orderGroupId) {
            return;
        }

        if ($this->selectedKitchenId !== null) {
            $this->validate([
                'selectedKitchenId' => 'exists:users,id',
            ]);
        }

        $group = OrderGroup::with(['kitchen', 'orders'])->findOrFail($this->orderGroupId);

        $previousKitchenId = $group->kitchen_id;
        $fromKitchen = $group->kitchen?->name ?? 'Unassigned';

        $group->update([
            'kitchen_id' => $this->selectedKitchenId ?: null,
            'updated_by' => Auth::id(),
        ]);

        $group->refresh()->load('kitchen');
        $toKitchen = $group->kitchen?->name ?? 'Unassigned';

        if ((int) ($previousKitchenId ?? 0) !== (int) ($group->kitchen_id ?? 0)) {
            foreach ($group->orders as $order) {
                \App\Support\OrderAudit::record($order, 'forwarded_to_kitchen', [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'from_kitchen_id' => $previousKitchenId,
                    'to_kitchen_id' => $group->kitchen_id,
                    'from_kitchen' => $fromKitchen,
                    'to_kitchen' => $toKitchen,
                    'source' => $order->package_subscription_id ? 'package' : 'menu',
                ], Auth::id());
            }
        }

        // First-time kitchen assignment advances pending orders to processing (push + UI).
        if ($this->selectedKitchenId && ! $previousKitchenId) {
            \App\Support\OrderKitchenAcceptance::markGroupOrdersProcessing($group, Auth::id());
        }

        $this->dispatch('order-group-kitchen-changed');
        $this->closeModal();
    }

    protected function fetchKitchens(): array
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'kitchen'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.operation.assign-kitchen-modal');
    }
}
