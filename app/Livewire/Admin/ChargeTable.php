<?php

namespace App\Livewire\Admin;

use App\Models\Area;
use App\Models\Charge;
use App\Models\MenuItem;
use App\Models\OrderCharge;
use App\Models\PackageSubscriptionCharge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ChargeTable extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $category = Charge::CATEGORY_DELIVERY;

    public string $description = '';

    public $amount = 50;

    public string $calculation = Charge::CALC_PER_DELIVERY;

    public $area_id = null;

    public $menu_item_id = null;

    public string $applies_to = Charge::APPLIES_BOTH;

    public $starts_at = null;

    public $ends_at = null;

    public bool $is_active = true;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $charge = Charge::query()->findOrFail($id);
        $this->editingId = $charge->id;
        $this->name = $charge->name;
        $this->category = $charge->category;
        $this->description = (string) ($charge->description ?? '');
        $this->amount = $charge->amount;
        $this->calculation = $charge->calculation;
        $this->area_id = $charge->area_id;
        $this->menu_item_id = $charge->menu_item_id;
        $this->applies_to = $charge->applies_to;
        $this->starts_at = $charge->starts_at?->timezone('Asia/Dhaka')->format('Y-m-d\TH:i');
        $this->ends_at = $charge->ends_at?->timezone('Asia/Dhaka')->format('Y-m-d\TH:i');
        $this->is_active = (bool) $charge->is_active;
        $this->showForm = true;
        $this->errorMessage = '';
        $this->statusMessage = '';
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';

        $data = $this->validate([
            'name' => 'required|string|min:2|max:120',
            'category' => ['required', Rule::in(Charge::categories())],
            'description' => 'nullable|string|max:255',
            'amount' => 'required|integer|min:1',
            'calculation' => ['required', Rule::in(Charge::calculations())],
            'area_id' => 'nullable|exists:areas,id',
            'menu_item_id' => 'nullable|exists:menu_items,id',
            'applies_to' => 'required|in:orders,packages,both',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ]);

        $payload = [
            'name' => $data['name'],
            'category' => $data['category'],
            'description' => $data['description'] ?: null,
            'amount' => (int) $data['amount'],
            'calculation' => $data['calculation'],
            'area_id' => $data['area_id'] !== null && $data['area_id'] !== '' ? (int) $data['area_id'] : null,
            'menu_item_id' => $data['menu_item_id'] !== null && $data['menu_item_id'] !== ''
                ? (int) $data['menu_item_id']
                : null,
            'applies_to' => $data['applies_to'],
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
            'is_active' => (bool) $this->is_active,
            'updated_by' => Auth::id(),
        ];

        if ($this->editingId) {
            Charge::query()->whereKey($this->editingId)->update($payload);
            $this->statusMessage = 'Charge updated.';
        } else {
            $payload['created_by'] = Auth::id();
            Charge::create($payload);
            $this->statusMessage = 'Charge created.';
        }

        $this->showForm = false;
        $this->resetForm();
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $charge = Charge::query()->findOrFail($id);
        $charge->update([
            'is_active' => ! $charge->is_active,
            'updated_by' => Auth::id(),
        ]);
        $this->statusMessage = $charge->is_active ? 'Charge activated.' : 'Charge deactivated.';
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->category = Charge::CATEGORY_DELIVERY;
        $this->description = '';
        $this->amount = 50;
        $this->calculation = Charge::CALC_PER_DELIVERY;
        $this->area_id = null;
        $this->menu_item_id = null;
        $this->applies_to = Charge::APPLIES_BOTH;
        $this->starts_at = null;
        $this->ends_at = null;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $charges = Charge::query()
            ->with(['area:id,name', 'menuItem:id,name'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('category', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->latest()
            ->paginate(15);

        $recentUsages = OrderCharge::query()
            ->with(['order:id,user_id,delivery_date', 'order.user:id,first_name,last_name,company_name,mobile'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (OrderCharge $row) => [
                'kind' => 'order',
                'id' => $row->id,
                'name' => $row->name,
                'amount' => $row->amount,
                'created_at' => $row->created_at,
                'label' => 'Order #'.$row->order_id,
                'user' => $row->order?->user,
            ]);

        $packageUsages = PackageSubscriptionCharge::query()
            ->with(['subscription:id,user_id', 'subscription.user:id,first_name,last_name,company_name,mobile'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (PackageSubscriptionCharge $row) => [
                'kind' => 'package',
                'id' => $row->id,
                'name' => $row->name,
                'amount' => $row->amount,
                'created_at' => $row->created_at,
                'label' => 'Package #'.$row->package_subscription_id,
                'user' => $row->subscription?->user,
            ]);

        $recentUsages = $recentUsages->concat($packageUsages)
            ->sortByDesc(fn ($row) => $row['created_at']?->timestamp ?? 0)
            ->take(20)
            ->values();

        return view('livewire.admin.charge-table', [
            'charges' => $charges,
            'areas' => Area::query()->with('city:id,name')->orderBy('name')->get(),
            'menus' => MenuItem::query()->orderBy('name')->get(['id', 'name', 'price']),
            'recentUsages' => $recentUsages,
        ]);
    }
}
