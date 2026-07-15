<?php

namespace App\Livewire\Kitchen;

use App\Livewire\Kitchen\Concerns\FormatsOrderGroups;
use App\Models\OrderGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class MiddoOrderGroups extends Component
{
    use FormatsOrderGroups;
    use WithPagination;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function acceptOrder(int $orderGroupId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $kitchenId = Auth::id();

        if (! $kitchenId) {
            $this->errorMessage = 'You must be logged in to accept an order group.';

            return;
        }

        try {
            $accepted = DB::transaction(function () use ($orderGroupId, $kitchenId) {
                $group = OrderGroup::query()
                    ->whereKey($orderGroupId)
                    ->lockForUpdate()
                    ->first();

                if (! $group) {
                    throw new \RuntimeException('Order group not found.');
                }

                if ($group->kitchen_id !== null) {
                    throw new \RuntimeException('This order group was already accepted by another kitchen.');
                }

                $group->update([
                    'kitchen_id' => $kitchenId,
                    'updated_by' => $kitchenId,
                ]);

                return $group->name;
            });

            $this->statusMessage = "Accepted {$accepted}. It is now assigned to your kitchen.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not accept this order group.';
        }
    }

    public function render()
    {
        $today = now('Asia/Dhaka')->toDateString();

        $groups = OrderGroup::with([
            'menuItem',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'user'])
                ->orderBy('delivery_time'),
        ])
            ->whereNull('kitchen_id')
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders')
            ->orderBy('delivery_date')
            ->orderBy('name')
            ->paginate(20);

        $offset = ($groups->currentPage() - 1) * $groups->perPage();
        $groupNodes = $this->buildGroupNodes($groups->getCollection(), $offset);

        return view('livewire.kitchen.middo-order-groups', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
        ])->layout('layouts.private.app', ['title' => 'Middo Order Groups']);
    }
}
