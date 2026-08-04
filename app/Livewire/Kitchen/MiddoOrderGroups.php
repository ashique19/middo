<?php

namespace App\Livewire\Kitchen;

use App\Livewire\Kitchen\Concerns\FormatsOrderGroups;
use App\Models\OrderGroup;
use App\Models\OrderGroupEvent;
use App\Support\KitchenAcceptWindow;
use App\Support\KitchenCapacity;
use App\Support\OrderGroupKitchenAssignment;
use Illuminate\Support\Facades\Auth;
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

    public ?int $declineGroupId = null;

    public string $declineReason = '';

    public function acceptOrder(int $orderGroupId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $kitchen = Auth::user();

        if (! $kitchen) {
            $this->errorMessage = 'You must be logged in to accept an order group.';

            return;
        }

        try {
            $group = OrderGroup::query()->findOrFail($orderGroupId);
            $accepted = OrderGroupKitchenAssignment::accept($group, $kitchen);
            $this->statusMessage = "Accepted {$accepted->name}. It is now assigned to your kitchen.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not accept this order group.';
        }
    }

    public function openDecline(int $orderGroupId): void
    {
        $this->errorMessage = null;
        $this->declineGroupId = $orderGroupId;
        $this->declineReason = '';
    }

    public function cancelDecline(): void
    {
        $this->declineGroupId = null;
        $this->declineReason = '';
    }

    public function confirmDecline(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $kitchen = Auth::user();
        if (! $kitchen || ! $this->declineGroupId) {
            return;
        }

        try {
            $this->validate([
                'declineReason' => 'required|string|min:3|max:255',
            ]);

            $group = OrderGroup::query()->findOrFail($this->declineGroupId);
            OrderGroupKitchenAssignment::decline($group, $kitchen, $this->declineReason);
            $this->statusMessage = "Declined {$group->name}. It stays in the Middo pool for other kitchens.";
            $this->cancelDecline();
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not decline this order group.';
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
        $kitchenId = (int) Auth::id();
        $declinedIds = $kitchenId
            ? OrderGroupKitchenAssignment::declinedGroupIdsForKitchenToday($kitchenId)
            : [];

        $groups = OrderGroup::with([
            'menuItem',
            'orders' => fn ($query) => $query
                ->with(['menuItem', 'user', 'packageSubscription.package'])
                ->where('order_status', '!=', 'cancelled')
                ->orderBy('delivery_time'),
            'events' => fn ($query) => $query
                ->whereIn('type', [OrderGroupEvent::TYPE_SHORTAGE, OrderGroupEvent::TYPE_DECLINE])
                ->latest()
                ->limit(3),
        ])
            ->whereNull('kitchen_id')
            ->whereDate('delivery_date', '>=', $today)
            ->whereHas('orders', fn ($query) => $query->where('order_status', '!=', 'cancelled'))
            ->when($declinedIds !== [], fn ($q) => $q->whereNotIn('id', $declinedIds))
            ->orderBy('delivery_date')
            ->orderBy('name')
            ->paginate(20);

        $offset = ($groups->currentPage() - 1) * $groups->perPage();
        $groupNodes = $this->buildGroupNodes($groups->getCollection(), $offset);

        $groupNodes = collect($groupNodes)
            ->map(function (array $node) use ($groups) {
                /** @var OrderGroup|null $group */
                $group = $groups->getCollection()->firstWhere('id', $node['id']);
                $window = $group
                    ? KitchenAcceptWindow::statusPayload($group)
                    : ['is_open' => false, 'state' => 'closed', 'label' => '—', 'open_at_iso' => '', 'close_at_iso' => ''];

                $recentShortage = $group?->events
                    ?->first(fn (OrderGroupEvent $e) => $e->type === OrderGroupEvent::TYPE_SHORTAGE);

                return array_merge($node, [
                    'accept_window' => $window,
                    'can_accept' => $window['is_open'] && $this->remainingSlots > 0,
                    'had_shortage' => $recentShortage !== null,
                    'shortage_reason' => $recentShortage?->reason,
                ]);
            })
            ->all();

        return view('livewire.kitchen.middo-order-groups', [
            'groups' => $groups,
            'groupNodes' => $groupNodes,
            'atCapacity' => $this->remainingSlots <= 0,
        ])->layout('kitchen.layout.app', ['title' => 'Middo Order Groups']);
    }
}
