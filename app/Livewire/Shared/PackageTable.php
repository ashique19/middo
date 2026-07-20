<?php

namespace App\Livewire\Shared;

use App\Models\MealPackage;
use App\Models\MealPackageDay;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class PackageTable extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $canManage = false;

    public bool $canCreate = false;

    public bool $canPublish = false;

    public function mount(): void
    {
        $role = Auth::user()?->role?->name;
        $this->canPublish = $role === 'admin';
        $this->canManage = $role === 'admin';
        $this->canCreate = in_array($role, ['admin', 'operation'], true);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function clonePackage(int $id): void
    {
        abort_unless($this->canCreate, 403);

        $source = MealPackage::with('days')->findOrFail($id);
        $start = now('Asia/Dhaka')->addDay()->startOfDay();
        $end = $start->copy()->addDays(max(1, (int) $source->duration_days) - 1);
        $offsetDays = $source->start_date
            ? $source->start_date->copy()->startOfDay()->diffInDays($start, false)
            : 0;

        $clone = MealPackage::create([
            'name' => $source->name.' (copy)',
            'summary' => $source->summary,
            'price_per_day' => $source->price_per_day,
            'diet_tag' => $source->diet_tag,
            'duration_days' => $source->duration_days,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'thumbnail' => null,
            'status' => MealPackage::STATUS_DRAFT,
            'display_order' => $source->display_order,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        foreach ($source->days as $day) {
            $newDate = $day->delivery_date->copy()->addDays($offsetDays);
            if ($newDate->lt($start) || $newDate->gt($end)) {
                continue;
            }

            MealPackageDay::create([
                'meal_package_id' => $clone->id,
                'delivery_date' => $newDate->toDateString(),
                'menu_item_id' => $day->menu_item_id,
            ]);
        }

        $route = Auth::user()?->role?->name === 'admin'
            ? route('admin.packages.edit', $clone)
            : route('operation.packages.edit', $clone);

        $this->redirect($route);
    }

    public function deletePackage(int $id): void
    {
        $this->authorizeManage();

        $package = MealPackage::findOrFail($id);

        if ($package->subscriptions()->exists()) {
            $package->update([
                'status' => MealPackage::STATUS_ARCHIVED,
                'updated_by' => Auth::id(),
            ]);
            $this->dispatch('package-updated');

            return;
        }

        if ($package->thumbnail && file_exists(public_path($package->thumbnail))) {
            @unlink(public_path($package->thumbnail));
        }

        $package->delete();
        $this->dispatch('package-updated');
    }

    public function togglePublish(int $id): void
    {
        abort_unless($this->canPublish, 403);

        $package = MealPackage::withCount('days')->findOrFail($id);

        if ($package->status === MealPackage::STATUS_PUBLISHED) {
            $package->update([
                'status' => MealPackage::STATUS_DRAFT,
                'updated_by' => Auth::id(),
            ]);
        } else {
            if ((int) $package->days_count < 1) {
                session()->flash('package_error', 'Assign at least one menu day before publishing.');

                return;
            }

            $package->update([
                'status' => MealPackage::STATUS_PUBLISHED,
                'updated_by' => Auth::id(),
            ]);
        }

        $this->dispatch('package-updated');
    }

    #[On('package-updated')]
    public function refreshTable(): void {}

    protected function authorizeManage(): void
    {
        abort_unless($this->canManage, 403);
    }

    public function render()
    {
        $packages = MealPackage::query()
            ->withCount('days')
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->orderBy('display_order')
            ->orderByDesc('start_date')
            ->paginate(10);

        return view('livewire.shared.packages.table', [
            'packages' => $packages,
        ]);
    }
}
