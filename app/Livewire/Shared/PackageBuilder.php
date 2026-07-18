<?php

namespace App\Livewire\Shared;

use App\Models\MealPackage;
use App\Models\MealPackageDay;
use App\Models\MenuItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class PackageBuilder extends Component
{
    use WithFileUploads;

    public ?int $packageId = null;

    public bool $canManage = false;

    public string $name = '';

    public ?string $summary = null;

    public int $price_per_day = 79;

    public string $diet_tag = 'classic';

    public int $duration_days = 30;

    public string $start_date = '';

    public string $end_date = '';

    public int $display_order = 0;

    public string $status = 'draft';

    public $thumbnail;

    public ?string $existingThumbnail = null;

    /** @var array<string, int|null> date => menu_item_id */
    public array $dayAssignments = [];

    public ?string $assignDate = null;

    public ?int $assignMenuItemId = null;

    public ?int $bulkMenuItemId = null;

    public string $errorMessage = '';

    public string $successMessage = '';

    public function mount(?int $package = null): void
    {
        $this->canManage = Auth::user()?->role?->name === 'admin';
        $this->packageId = $package;

        if ($package) {
            $model = MealPackage::with('days')->findOrFail($package);
            $this->fillFromModel($model);
        } else {
            abort_unless($this->canManage, 403);
            $start = now('Asia/Dhaka')->addDay()->startOfDay();
            $this->start_date = $start->toDateString();
            $this->end_date = $start->copy()->addDays($this->duration_days - 1)->toDateString();
            $this->rebuildDayKeys();
        }
    }

    protected function fillFromModel(MealPackage $model): void
    {
        $this->name = $model->name;
        $this->summary = $model->summary;
        $this->price_per_day = (int) $model->price_per_day;
        $this->diet_tag = $model->diet_tag;
        $this->duration_days = (int) $model->duration_days;
        $this->start_date = $model->start_date->toDateString();
        $this->end_date = $model->end_date->toDateString();
        $this->display_order = (int) $model->display_order;
        $this->status = $model->status;
        $this->existingThumbnail = $model->thumbnail;

        $this->rebuildDayKeys();
        foreach ($model->days as $day) {
            $this->dayAssignments[$day->delivery_date->toDateString()] = $day->menu_item_id;
        }
    }

    public function updatedStartDate(): void
    {
        $this->syncEndDate();
        $this->rebuildDayKeys();
    }

    public function updatedDurationDays(): void
    {
        $this->duration_days = max(1, min(60, (int) $this->duration_days));
        $this->syncEndDate();
        $this->rebuildDayKeys();
    }

    protected function syncEndDate(): void
    {
        if (! $this->start_date) {
            return;
        }

        $this->end_date = Carbon::parse($this->start_date)
            ->addDays($this->duration_days - 1)
            ->toDateString();
    }

    protected function rebuildDayKeys(): void
    {
        if (! $this->start_date || ! $this->end_date) {
            return;
        }

        $cursor = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date)->startOfDay();
        $next = [];

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $next[$key] = $this->dayAssignments[$key] ?? null;
            $cursor->addDay();
        }

        $this->dayAssignments = $next;
    }

    public function openAssign(string $date): void
    {
        abort_unless($this->canManage, 403);
        $this->assignDate = $date;
        $this->assignMenuItemId = $this->dayAssignments[$date] ?? null;
    }

    public function closeAssign(): void
    {
        $this->assignDate = null;
        $this->assignMenuItemId = null;
    }

    public function confirmAssign(): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->assignDate || ! $this->assignMenuItemId) {
            return;
        }

        $this->dayAssignments[$this->assignDate] = (int) $this->assignMenuItemId;
        $this->closeAssign();
    }

    public function clearDay(string $date): void
    {
        abort_unless($this->canManage, 403);
        $this->dayAssignments[$date] = null;
    }

    public function fillWeekdays(): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->bulkMenuItemId) {
            $this->errorMessage = 'Pick a menu item to fill weekdays.';

            return;
        }

        foreach ($this->dayAssignments as $date => $_) {
            $dow = Carbon::parse($date)->dayOfWeek;
            if ($dow !== Carbon::FRIDAY && $dow !== Carbon::SATURDAY) {
                $this->dayAssignments[$date] = (int) $this->bulkMenuItemId;
            }
        }

        $this->errorMessage = '';
    }

    public function fillAll(): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->bulkMenuItemId) {
            $this->errorMessage = 'Pick a menu item to fill all days.';

            return;
        }

        foreach ($this->dayAssignments as $date => $_) {
            $this->dayAssignments[$date] = (int) $this->bulkMenuItemId;
        }

        $this->errorMessage = '';
    }

    public function clearWeekends(): void
    {
        abort_unless($this->canManage, 403);

        foreach ($this->dayAssignments as $date => $_) {
            $dow = Carbon::parse($date)->dayOfWeek;
            if ($dow === Carbon::FRIDAY || $dow === Carbon::SATURDAY) {
                $this->dayAssignments[$date] = null;
            }
        }
    }

    public function save(): void
    {
        abort_unless($this->canManage, 403);

        $this->validate([
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string|max:2000',
            'price_per_day' => 'required|integer|min:1',
            'diet_tag' => 'required|in:'.implode(',', MealPackage::DIET_TAGS),
            'duration_days' => 'required|integer|min:1|max:60',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'display_order' => 'integer',
            'status' => 'required|in:draft,published,archived',
            'thumbnail' => 'nullable|image|max:2048',
        ]);

        $this->syncEndDate();
        $this->rebuildDayKeys();

        $assigned = collect($this->dayAssignments)->filter()->count();
        if ($this->status === MealPackage::STATUS_PUBLISHED && $assigned < 1) {
            $this->errorMessage = 'Assign at least one menu day before publishing.';

            return;
        }

        DB::transaction(function () {
            $payload = [
                'name' => $this->name,
                'summary' => $this->summary,
                'price_per_day' => $this->price_per_day,
                'diet_tag' => $this->diet_tag,
                'duration_days' => $this->duration_days,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'display_order' => $this->display_order,
                'status' => $this->status,
                'updated_by' => Auth::id(),
            ];

            if ($this->packageId) {
                $package = MealPackage::findOrFail($this->packageId);
                $package->update($payload);
            } else {
                $payload['created_by'] = Auth::id();
                $package = MealPackage::create($payload);
                $this->packageId = $package->id;
            }

            if ($this->thumbnail) {
                $directory = public_path('img/packages');
                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                $ext = method_exists($this->thumbnail, 'getClientOriginalExtension')
                    ? ($this->thumbnail->getClientOriginalExtension() ?: 'jpg')
                    : 'jpg';
                $filename = 'package-'.$package->id.'-'.time().'.'.$ext;
                if ($package->thumbnail && file_exists(public_path($package->thumbnail))) {
                    @unlink(public_path($package->thumbnail));
                }
                file_put_contents(
                    $directory.'/'.$filename,
                    file_get_contents($this->thumbnail->getRealPath())
                );
                $package->update(['thumbnail' => 'img/packages/'.$filename]);
                $this->existingThumbnail = $package->thumbnail;
                $this->thumbnail = null;
            }

            MealPackageDay::where('meal_package_id', $package->id)->delete();

            foreach ($this->dayAssignments as $date => $menuItemId) {
                if (! $menuItemId) {
                    continue;
                }

                MealPackageDay::create([
                    'meal_package_id' => $package->id,
                    'delivery_date' => $date,
                    'menu_item_id' => $menuItemId,
                ]);
            }
        });

        $this->successMessage = 'Package saved.';
        $this->errorMessage = '';
        $this->dispatch('package-updated');
    }

    public function render()
    {
        $menuItems = MenuItem::query()->orderBy('name')->get(['id', 'name', 'price', 'thumbnail']);
        $menuLookup = $menuItems->keyBy('id');

        return view('livewire.shared.packages.builder', [
            'menuItems' => $menuItems,
            'menuLookup' => $menuLookup,
        ])->layout('layouts.private.app', ['title' => $this->packageId ? 'Edit Package' : 'Create Package']);
    }
}
