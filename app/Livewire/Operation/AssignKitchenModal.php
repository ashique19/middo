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

        $group = OrderGroup::findOrFail($this->orderGroupId);

        $group->update([
            'kitchen_id' => $this->selectedKitchenId ?: null,
            'updated_by' => Auth::id(),
        ]);

        $this->dispatch('order-group-kitchen-changed');
        $this->closeModal();
    }

    protected function fetchKitchens(): array
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'kitchen'))
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
