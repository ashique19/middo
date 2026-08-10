<?php

namespace App\Livewire\Admin;

use App\Models\Area;
use App\Models\Charge;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\MenuItem;
use App\Support\CouponService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class CouponTable extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $type = Coupon::TYPE_FIXED;

    public $value = 100;

    public $min_subtotal = 0;

    public $max_discount = null;

    public $usage_limit = null;

    public $per_user_limit = 1;

    public string $applies_to = Coupon::APPLIES_BOTH;

    public $waive_charge_category = '';

    public $waive_charge_id = null;

    /** @var list<int|string> */
    public array $eligible_menu_item_ids = [];

    /** @var list<int|string> */
    public array $eligible_area_ids = [];

    /** @var list<int|string> */
    public array $eligible_company_ids = [];

    public bool $first_order_only = false;

    public $min_quantity = null;

    public $starts_at = null;

    public $ends_at = null;

    public bool $is_active = true;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        if ($this->type === Coupon::TYPE_WAIVE_CHARGE) {
            $this->value = 0;
            $this->min_subtotal = 0;
        } elseif ((int) $this->value < 1) {
            $this->value = 100;
        }
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $coupon = Coupon::query()->findOrFail($id);
        $this->editingId = $coupon->id;
        $this->code = $coupon->code;
        $this->name = $coupon->name;
        $this->description = (string) ($coupon->description ?? '');
        $this->type = $coupon->type;
        $this->value = $coupon->value;
        $this->min_subtotal = $coupon->min_subtotal;
        $this->max_discount = $coupon->max_discount;
        $this->usage_limit = $coupon->usage_limit;
        $this->per_user_limit = $coupon->per_user_limit;
        $this->applies_to = $coupon->applies_to;
        $this->waive_charge_category = (string) ($coupon->waive_charge_category ?? '');
        $this->waive_charge_id = $coupon->waive_charge_id;
        $this->eligible_menu_item_ids = array_map('strval', $coupon->eligibleMenuItemIds());
        $this->eligible_area_ids = array_map('strval', $coupon->eligibleAreaIds());
        $this->eligible_company_ids = array_map('strval', $coupon->eligibleCompanyIds());
        $this->first_order_only = $coupon->firstOrderOnly();
        $this->min_quantity = $coupon->minQuantity();
        $this->starts_at = $coupon->starts_at?->timezone('Asia/Dhaka')->format('Y-m-d\TH:i');
        $this->ends_at = $coupon->ends_at?->timezone('Asia/Dhaka')->format('Y-m-d\TH:i');
        $this->is_active = (bool) $coupon->is_active;
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
        $this->code = CouponService::normalizeCode($this->code);

        $data = $this->validate([
            'code' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($this->editingId),
            ],
            'name' => 'required|string|min:2|max:120',
            'description' => 'nullable|string|max:255',
            'type' => 'required|in:percent,fixed,waive_charge',
            'value' => 'nullable|integer|min:0',
            'min_subtotal' => 'nullable|integer|min:0',
            'max_discount' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1|max:100',
            'applies_to' => 'required|in:orders,packages,both',
            'waive_charge_category' => 'nullable|in:delivery,handling,packaging,other',
            'waive_charge_id' => 'nullable|integer|exists:charges,id',
            'eligible_menu_item_ids' => 'array',
            'eligible_menu_item_ids.*' => 'integer|exists:menu_items,id',
            'eligible_area_ids' => 'array',
            'eligible_area_ids.*' => 'integer|exists:areas,id',
            'eligible_company_ids' => 'array',
            'eligible_company_ids.*' => 'integer|exists:companies,id',
            'first_order_only' => 'boolean',
            'min_quantity' => 'nullable|integer|min:1|max:10000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ], [
            'code.regex' => 'Use letters, numbers, dashes, or underscores only.',
        ]);

        if ($data['type'] === Coupon::TYPE_PERCENT) {
            if ((int) ($data['value'] ?? 0) < 1 || (int) $data['value'] > 100) {
                $this->addError('value', 'Percent coupons must be between 1 and 100.');

                return;
            }
        }

        if ($data['type'] === Coupon::TYPE_FIXED && (int) ($data['value'] ?? 0) < 1) {
            $this->addError('value', 'Fixed coupons need a ৳ value of at least 1.');

            return;
        }

        $eligibility = [
            'menu_item_ids' => array_values(array_unique(array_map('intval', $data['eligible_menu_item_ids'] ?? []))),
            'area_ids' => array_values(array_unique(array_map('intval', $data['eligible_area_ids'] ?? []))),
            'company_ids' => array_values(array_unique(array_map('intval', $data['eligible_company_ids'] ?? []))),
            'first_order_only' => (bool) $this->first_order_only,
            'min_quantity' => isset($data['min_quantity']) && $data['min_quantity'] !== null && $data['min_quantity'] !== ''
                ? (int) $data['min_quantity']
                : null,
        ];

        $payload = [
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'type' => $data['type'],
            'value' => $data['type'] === Coupon::TYPE_WAIVE_CHARGE ? 0 : (int) $data['value'],
            'waive_charge_category' => $data['type'] === Coupon::TYPE_WAIVE_CHARGE
                ? ($data['waive_charge_category'] ?: null)
                : null,
            'waive_charge_id' => $data['type'] === Coupon::TYPE_WAIVE_CHARGE
                ? ($data['waive_charge_id'] ? (int) $data['waive_charge_id'] : null)
                : null,
            'min_subtotal' => $data['type'] === Coupon::TYPE_WAIVE_CHARGE
                ? 0
                : (int) ($data['min_subtotal'] ?? 0),
            'max_discount' => $data['max_discount'] !== null && $data['max_discount'] !== ''
                ? (int) $data['max_discount']
                : null,
            'usage_limit' => $data['usage_limit'] !== null && $data['usage_limit'] !== ''
                ? (int) $data['usage_limit']
                : null,
            'per_user_limit' => (int) $data['per_user_limit'],
            'applies_to' => $data['applies_to'],
            'eligibility' => $eligibility,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
            'is_active' => (bool) $this->is_active,
            'updated_by' => Auth::id(),
        ];

        if ($this->editingId) {
            Coupon::query()->whereKey($this->editingId)->update($payload);
            $this->statusMessage = 'Coupon updated.';
        } else {
            $payload['created_by'] = Auth::id();
            Coupon::create($payload);
            $this->statusMessage = 'Coupon created.';
        }

        $this->showForm = false;
        $this->resetForm();
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $coupon = Coupon::query()->findOrFail($id);
        $coupon->update([
            'is_active' => ! $coupon->is_active,
            'updated_by' => Auth::id(),
        ]);
        $this->statusMessage = $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.';
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->type = Coupon::TYPE_FIXED;
        $this->value = 100;
        $this->min_subtotal = 0;
        $this->max_discount = null;
        $this->usage_limit = null;
        $this->per_user_limit = 1;
        $this->applies_to = Coupon::APPLIES_BOTH;
        $this->waive_charge_category = '';
        $this->waive_charge_id = null;
        $this->eligible_menu_item_ids = [];
        $this->eligible_area_ids = [];
        $this->eligible_company_ids = [];
        $this->first_order_only = false;
        $this->min_quantity = null;
        $this->starts_at = null;
        $this->ends_at = null;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $coupons = Coupon::query()
            ->withCount('redemptions')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', $term)
                        ->orWhere('name', 'like', $term);
                });
            })
            ->latest()
            ->paginate(15);

        $recentRedemptions = CouponRedemption::query()
            ->with(['coupon:id,code,name', 'user:id,first_name,last_name,company_name,mobile'])
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.admin.coupon-table', [
            'coupons' => $coupons,
            'recentRedemptions' => $recentRedemptions,
            'menus' => MenuItem::query()->orderBy('name')->get(['id', 'name']),
            'areas' => Area::query()->with('city:id,name')->orderBy('name')->get(['id', 'name', 'city_id']),
            'companies' => Company::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'charges' => Charge::query()->orderBy('name')->get(['id', 'name', 'category', 'amount']),
        ]);
    }
}
