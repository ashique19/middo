<?php

namespace App\Livewire\Operation;

use App\Models\OrderGroup;
use App\Models\User;
use App\Support\KitchenCapacity;
use App\Support\OrderKitchenAcceptance;
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
        $this->kitchens = $this->fetchKitchens($group->kitchen_id);
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

        $group = OrderGroup::with('orders')->findOrFail($this->orderGroupId);

        $previousKitchenId = $group->kitchen_id;
        $nextKitchenId = $this->selectedKitchenId ?: null;
        $kitchenChanging = (int) ($previousKitchenId ?? 0) !== (int) ($nextKitchenId ?? 0);

        if ($kitchenChanging) {
            $lockedStatuses = ['packed', 'on_the_way_to_delivery', 'delivered', 'delivered_and_paid'];
            $hasLockedOrders = $group->orders->contains(function ($order) use ($lockedStatuses) {
                return in_array($order->order_status, $lockedStatuses, true)
                    || $order->dispatched_at !== null;
            });

            if ($hasLockedOrders) {
                $this->addError(
                    'selectedKitchenId',
                    'Cannot reassign kitchen after orders in this group are packed or dispatched.'
                );

                return;
            }
        }

        if ($nextKitchenId && $kitchenChanging) {
            $kitchen = User::query()->find($nextKitchenId);
            if ($kitchen) {
                try {
                    KitchenCapacity::assertCanAccept($kitchen);
                } catch (\RuntimeException $e) {
                    $this->addError('selectedKitchenId', $e->getMessage());

                    return;
                }
            }
        }

        $group->update([
            'kitchen_id' => $nextKitchenId,
            'updated_by' => Auth::id(),
        ]);

        // First-time kitchen assignment advances pending orders to processing (push + UI).
        if ($nextKitchenId && ! $previousKitchenId) {
            OrderKitchenAcceptance::markGroupOrdersProcessing($group, Auth::id());
        }

        $this->dispatch('order-group-kitchen-changed');
        $this->closeModal();
    }

    protected function fetchKitchens(?int $currentKitchenId = null): array
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'kitchen'))
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (User $user) use ($currentKitchenId) {
                $remaining = KitchenCapacity::remainingSlots($user);
                $atCapacity = $remaining <= 0 && (int) $user->id !== (int) $currentKitchenId;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'tier' => $user->kitchen_tier,
                    'remaining_slots' => $remaining,
                    'at_capacity' => $atCapacity,
                    'label' => sprintf(
                        '%s · %s · %d slot(s) left',
                        $user->name,
                        KitchenCapacity::effectiveAllowedOpenGroups($user) > 0
                            ? ucfirst((string) ($user->kitchen_tier ?: 'silver'))
                            : 'no tier',
                        $remaining
                    ),
                ];
            })
            ->all();
    }

    public function render()
    {
        return view('livewire.operation.assign-kitchen-modal');
    }
}
