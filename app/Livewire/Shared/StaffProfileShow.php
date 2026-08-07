<?php

namespace App\Livewire\Shared;

use App\Livewire\Concerns\WithOrdersListView;
use App\Models\Area;
use App\Models\City;
use App\Models\KitchenHour;
use App\Models\Order;
use App\Models\User;
use App\Support\KitchenActivation;
use App\Support\KitchenTier;
use App\Support\MiddoSettings;
use App\Support\OrdersExcelExport;
use App\Support\PackageOrderPresenter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffProfileShow extends Component
{
    use WithOrdersListView;
    use WithPagination;

    public User $staff;

    /** @var 'kitchen'|'delivery' */
    public string $staffRole;

    public string $edit_kitchen_tier = KitchenTier::SILVER;

    public $edit_allowed_open_groups = 1;

    /** @var array<int, array{is_closed: bool, opens_at: string, closes_at: string}> */
    public array $hours = [];

    /** @var array<int, string> */
    public array $selectedAreaIds = [];

    public string $hoursStatusMessage = '';

    public string $hoursErrorMessage = '';

    public string $areasStatusMessage = '';

    public string $areasErrorMessage = '';

    public function mount(?User $kitchen = null, ?User $delivery = null): void
    {
        $viewerRole = Auth::user()?->role?->name;
        abort_unless(in_array($viewerRole, ['admin', 'operation'], true), 403);

        $staff = $kitchen ?? $delivery;
        abort_unless($staff, 404);

        $expected = $kitchen ? 'kitchen' : 'delivery';
        $staff->load(['role', 'city', 'area', 'areas']);
        abort_unless($staff->role?->name === $expected, 404);

        $this->staff = $staff;
        $this->staffRole = $expected;
        $this->syncKitchenEditFields();
        $this->loadHours();
        $this->syncRiderAreaFields();
    }

    protected function syncKitchenEditFields(): void
    {
        if ($this->staffRole !== 'kitchen') {
            return;
        }

        $this->edit_kitchen_tier = KitchenTier::normalize($this->staff->kitchen_tier);
        $this->edit_allowed_open_groups = $this->staff->allowed_open_groups
            ?? MiddoSettings::defaultAllowedOpenGroupsForTier($this->edit_kitchen_tier);
    }

    protected function syncRiderAreaFields(): void
    {
        if ($this->staffRole !== 'delivery') {
            return;
        }

        $pivotIds = $this->staff->areas->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->selectedAreaIds = $pivotIds !== []
            ? $pivotIds
            : ($this->staff->area_id ? [(string) $this->staff->area_id] : []);
    }

    protected function loadHours(): void
    {
        if ($this->staffRole !== 'kitchen') {
            return;
        }

        $existing = KitchenHour::query()
            ->where('user_id', $this->staff->id)
            ->get()
            ->keyBy('day_of_week');

        $this->hours = [];
        foreach (KitchenHour::DAYS as $day => $_label) {
            $row = $existing->get($day);
            if ($row) {
                $this->hours[$day] = [
                    'is_closed' => (bool) $row->is_closed,
                    'opens_at' => $row->opens_at ? substr((string) $row->opens_at, 0, 5) : '10:00',
                    'closes_at' => $row->closes_at ? substr((string) $row->closes_at, 0, 5) : '22:00',
                ];
            } else {
                $this->hours[$day] = [
                    'is_closed' => false,
                    'opens_at' => '10:00',
                    'closes_at' => '22:00',
                ];
            }
        }
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function backRoute(): string
    {
        if ($this->staffRole === 'delivery') {
            return route($this->rolePrefix().'.riders.index');
        }

        if ($this->rolePrefix() === 'admin' && $this->staff->status === 'pending') {
            return route('admin.kitchens.onboarding');
        }

        return route($this->rolePrefix().'.kitchens.'.($this->rolePrefix() === 'admin' ? 'active' : 'index'));
    }

    public function kitchenOrdersRoute(): ?string
    {
        if ($this->staffRole !== 'kitchen') {
            return null;
        }

        return route($this->rolePrefix().'.kitchens.orders', $this->staff);
    }

    public function canManageKitchenStatus(): bool
    {
        return $this->staffRole === 'kitchen'
            && Auth::user()?->role?->name === 'admin';
    }

    public function canEditKitchenCapacity(): bool
    {
        return $this->staffRole === 'kitchen'
            && in_array(Auth::user()?->role?->name, ['admin', 'operation'], true);
    }

    public function canEditKitchenHours(): bool
    {
        return $this->canEditKitchenCapacity();
    }

    public function canEditRiderAreas(): bool
    {
        return $this->staffRole === 'delivery'
            && in_array(Auth::user()?->role?->name, ['admin', 'operation'], true)
            && Schema::hasTable('area_user');
    }

    public function activate(): void
    {
        abort_unless($this->canManageKitchenStatus(), 403);

        KitchenActivation::activate($this->staff);
        $this->staff->refresh();
        $this->syncKitchenEditFields();

        session()->flash('message', "{$this->staff->name} activated.");
    }

    public function suspend(): void
    {
        abort_unless($this->canManageKitchenStatus(), 403);

        $this->staff->update(['status' => 'inactive']);
        $this->staff->refresh();

        session()->flash('message', "{$this->staff->name} suspended.");
    }

    public function saveKitchenCapacity(): void
    {
        abort_unless($this->canEditKitchenCapacity(), 403);

        $this->validate([
            'edit_kitchen_tier' => ['required', Rule::in(KitchenTier::all())],
            'edit_allowed_open_groups' => 'required|integer|min:0|max:100',
        ]);

        $this->staff->update([
            'kitchen_tier' => $this->edit_kitchen_tier,
            'allowed_open_groups' => (int) $this->edit_allowed_open_groups,
        ]);
        $this->staff->refresh();
        $this->syncKitchenEditFields();

        session()->flash('message', 'Kitchen tier and allowed open groups updated.');
    }

    public function resetAllowedToTierDefault(): void
    {
        abort_unless($this->canEditKitchenCapacity(), 403);

        $this->validate([
            'edit_kitchen_tier' => ['required', Rule::in(KitchenTier::all())],
        ]);

        $this->staff->update(['kitchen_tier' => $this->edit_kitchen_tier]);
        KitchenActivation::resetAllowedOpenGroupsToTierDefault($this->staff->fresh());
        $this->staff->refresh();
        $this->syncKitchenEditFields();

        session()->flash('message', 'Allowed open groups reset to '.$this->edit_kitchen_tier.' tier default ('.$this->staff->allowed_open_groups.').');
    }

    public function saveKitchenHours(): void
    {
        abort_unless($this->canEditKitchenHours(), 403);
        $this->hoursStatusMessage = '';
        $this->hoursErrorMessage = '';

        try {
            $this->validate([
                'hours' => 'required|array|size:7',
                'hours.*.is_closed' => 'boolean',
                'hours.*.opens_at' => 'nullable|date_format:H:i',
                'hours.*.closes_at' => 'nullable|date_format:H:i',
            ]);

            foreach ($this->hours as $day => $row) {
                if (! empty($row['is_closed'])) {
                    continue;
                }
                if (empty($row['opens_at']) || empty($row['closes_at'])) {
                    throw new \RuntimeException(KitchenHour::DAYS[(int) $day].': set open and close times, or mark closed.');
                }
                if ($row['opens_at'] >= $row['closes_at']) {
                    throw new \RuntimeException(KitchenHour::DAYS[(int) $day].': open time must be before close time.');
                }
            }

            DB::transaction(function () {
                foreach ($this->hours as $day => $row) {
                    $closed = (bool) ($row['is_closed'] ?? false);
                    KitchenHour::query()->updateOrCreate(
                        [
                            'user_id' => $this->staff->id,
                            'day_of_week' => (int) $day,
                        ],
                        [
                            'is_closed' => $closed,
                            'opens_at' => $closed ? null : ($row['opens_at'] ?: null),
                            'closes_at' => $closed ? null : ($row['closes_at'] ?: null),
                        ]
                    );
                }
            });

            $this->loadHours();
            $this->hoursStatusMessage = 'Weekly hours saved.';
        } catch (\Throwable $e) {
            $this->hoursErrorMessage = $e->getMessage() ?: 'Could not save hours.';
        }
    }

    public function saveRiderAreas(): void
    {
        abort_unless($this->canEditRiderAreas(), 403);
        $this->areasStatusMessage = '';
        $this->areasErrorMessage = '';

        try {
            $this->validate([
                'selectedAreaIds' => 'nullable|array',
                'selectedAreaIds.*' => 'integer|exists:areas,id',
            ]);

            $ids = array_values(array_unique(array_map('intval', $this->selectedAreaIds)));
            $this->staff->areas()->sync($ids);

            $primary = $ids[0] ?? null;
            if ($primary) {
                $area = Area::query()->find($primary);
                $this->staff->update([
                    'area_id' => $primary,
                    'city_id' => $area?->city_id,
                ]);
            } else {
                $this->staff->update(['area_id' => null]);
            }

            $this->staff->refresh()->load(['areas', 'area', 'city']);
            $this->syncRiderAreaFields();
            $this->areasStatusMessage = 'Service areas updated.';
        } catch (\Throwable $e) {
            $this->areasErrorMessage = $e->getMessage() ?: 'Could not save areas.';
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatOrder(Order $order): array
    {
        $party = $order->partyPayload();

        return array_merge([
            'id' => $order->id,
            'delivery_time' => $order->delivery_time,
            'delivery_date' => $order->delivery_date->toDateString(),
            'quantity' => $order->quantity,
            'order_status' => $order->order_status,
            'customer_name' => $party['customer_name'],
            'account_holder_name' => $party['account_holder_name'],
            'receiver_name' => $party['receiver_name'],
            'receiver_mobile' => $party['receiver_mobile'],
            'has_separate_receiver' => $party['has_separate_receiver'],
            'payment_status' => $order->payment_status,
            'payment_method' => $party['payment_method'],
            'payment_method_label' => $party['payment_method_label'],
            'total_amount' => $order->total_amount,
            'address' => $order->address,
            'menu_name' => $order->menuItem?->name ?? 'Custom Selection',
            'group_name' => $order->orderGroup?->name,
        ], PackageOrderPresenter::fields($order));
    }

    public function exportExcel(): StreamedResponse
    {
        $ordersQuery = Order::query()
            ->with(['menuItem', 'user', 'orderGroup', 'area', 'packageSubscription.package']);

        if ($this->staffRole === 'kitchen') {
            $ordersQuery->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $this->staff->id));
        } else {
            $ordersQuery->where('delivery_rider_id', $this->staff->id);
        }

        $orders = $ordersQuery
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        $slug = str($this->staff->name ?: $this->staffRole)->slug('-')->toString();

        return OrdersExcelExport::download(
            $orders,
            $this->staffRole.'-'.$slug.'-orders-'.now('Asia/Dhaka')->format('Y-m-d').'.csv'
        );
    }

    public function render()
    {
        $ordersQuery = Order::query()
            ->with(['menuItem', 'user', 'orderGroup', 'packageSubscription.package']);

        if ($this->staffRole === 'kitchen') {
            $ordersQuery->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $this->staff->id));
        } else {
            $ordersQuery->where('delivery_rider_id', $this->staff->id);
        }

        $orders = $ordersQuery
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate(15);

        $orderRows = collect($orders->items())
            ->map(fn (Order $order) => $this->formatOrder($order))
            ->values()
            ->all();

        $today = now('Asia/Dhaka')->toDateString();
        $baseStats = Order::query();
        if ($this->staffRole === 'kitchen') {
            $baseStats->whereHas('orderGroup', fn ($q) => $q->where('kitchen_id', $this->staff->id));
        } else {
            $baseStats->where('delivery_rider_id', $this->staff->id);
        }

        $stats = [
            'total_orders' => (clone $baseStats)->count(),
            'active_orders' => (clone $baseStats)
                ->whereIn('order_status', Order::ACTIVE_STATUSES)
                ->where('delivery_date', '>=', $today)
                ->count(),
            'delivered_orders' => (clone $baseStats)
                ->whereIn('order_status', ['delivered', 'delivered_and_paid'])
                ->count(),
        ];

        $title = ($this->staff->name ?: trim($this->staff->first_name.' '.$this->staff->last_name))
            .' · '.ucfirst($this->staffRole);

        $areaOptions = $this->canEditRiderAreas()
            ? City::query()->with(['areas' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get()
            : collect();

        return view('livewire.shared.staff.profile', [
            'orders' => $orders,
            'orderRows' => $orderRows,
            'stats' => $stats,
            'kitchenHours' => $this->staffRole === 'kitchen'
                ? $this->staff->kitchenHours()->orderBy('day_of_week')->get()
                : collect(),
            'dayLabels' => KitchenHour::DAYS,
            'areaOptions' => $areaOptions,
        ])->layout('layouts.private.app', ['title' => $title]);
    }
}
