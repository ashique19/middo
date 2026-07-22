<?php

namespace App\Livewire\Admin;

use App\Models\Coupon;
use App\Models\CouponRedemption;
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
            'type' => 'required|in:percent,fixed',
            'value' => 'required|integer|min:1',
            'min_subtotal' => 'nullable|integer|min:0',
            'max_discount' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1|max:100',
            'applies_to' => 'required|in:orders,packages,both',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ], [
            'code.regex' => 'Use letters, numbers, dashes, or underscores only.',
        ]);

        if ($data['type'] === Coupon::TYPE_PERCENT && (int) $data['value'] > 100) {
            $this->addError('value', 'Percent coupons cannot exceed 100.');

            return;
        }

        $payload = [
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'type' => $data['type'],
            'value' => (int) $data['value'],
            'min_subtotal' => (int) ($data['min_subtotal'] ?? 0),
            'max_discount' => $data['max_discount'] !== null && $data['max_discount'] !== ''
                ? (int) $data['max_discount']
                : null,
            'usage_limit' => $data['usage_limit'] !== null && $data['usage_limit'] !== ''
                ? (int) $data['usage_limit']
                : null,
            'per_user_limit' => (int) $data['per_user_limit'],
            'applies_to' => $data['applies_to'],
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
        ]);
    }
}
