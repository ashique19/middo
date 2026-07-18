<?php

namespace App\Livewire\Shared;

use App\Models\MealPackage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class PackageTable extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $canManage = false;

    public function mount(): void
    {
        $this->canManage = Auth::user()?->role?->name === 'admin';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
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
        $this->authorizeManage();

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
    public function refreshTable(): void
    {
    }

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
