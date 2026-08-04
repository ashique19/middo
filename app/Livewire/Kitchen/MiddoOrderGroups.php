<?php

namespace App\Livewire\Kitchen;

use App\Livewire\Kitchen\Concerns\FormatsOrderGroups;
use App\Models\OrderGroup;
use App\Models\User;
use App\Support\KitchenCapacity;
use App\Support\OrderKitchenAcceptance;
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

    public int $openGroupCount = 0;

    public int $allowedOpenGroups = 0;

    public int $remainingSlots = 0;

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
                $kitchen = User::query()->lockForUpdate()->find($kitchenId);
                if (! $kitchen) {
                    throw new \RuntimeException('Kitchen account not found.');
                }

                KitchenCapacity::assertCanAccept($kitchen);

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

                OrderKitchenAcceptance::markGroupOrdersProcessing($group, $kitchenId);

                return $group->name;
            });

            $this->statusMessage = "Accepted {$accepted}. It is now assigned to your kitchen.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not accept this order group.';
        }
    }

    protected function refreshCapacity(): void
    {
        $kitchen = Auth::user();
        if (! $kitchen) {
            $this->openGroupCount = 0;
            $this->allowedOpenGroups = 0;
            $this->remainingSlots = 0;

            return;
        }

        $this->openGroupCount = KitchenCapacity::openGroupCount((int) $kitchen->id);
        $this->allowedOpenGroups = KitchenCapacity::effectiveAllowedOpenGroups($kitchen);
        $this->remainingSlots = KitchenCapacity::remainingSlots($kitchen);
    }

    public function render()
    {
        $this->refreshCapacity();
        $today = now('Asia/Dhaka')->toDateString();

        $groups = OrderGroup::with([
            'menuItem',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'user', 'packageSubscription.package'])
                ->where('order_status', '!=', 'cancelled')
                ->orderBy('delivery_time'),
        ])
            ->whereNull('kitchen_id')
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders', fn ($query) => $query->where('order_status', '!=', 'cancelled'))
            ->orderBy('delivery_date')
            ->orderBy('name')
            ->paginate(20);

        $offset = ($groups->currentPage() - 1) * $groups->perPage();
        $groupNodes = $this->buildGroupNodes($groups->getCollection(), $offset);

        return view('livewire.kitchen.middo-order-groups', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
            'atCapacity' => $this->remainingSlots <= 0,
        ])->layout('layouts.private.app', ['title' => 'Middo Order Groups']);
    }
}
