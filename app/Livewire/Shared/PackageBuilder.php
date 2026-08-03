<?php

namespace App\Livewire\Shared;

use App\Models\MealPackage;
use App\Models\MealPackageDay;
use App\Models\MenuItem;
use App\Models\PackageSubscription;
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

    public bool $canPublish = false;

    public bool $daysSoftLocked = false;

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

    /** @var array<string, int|null> original assigned days used for soft-lock */
    public array $lockedDayAssignments = [];

    public ?string $assignDate = null;

    public ?int $assignMenuItemId = null;

    public ?int $bulkMenuItemId = null;

    public string $errorMessage = '';

    public string $successMessage = '';

    public function mount(?int $package = null): void
    {
        $role = Auth::user()?->role?->name;
        $this->canPublish = $role === 'admin';
        $this->packageId = $package;

        if ($package) {
            $model = MealPackage::with('days')->findOrFail($package);
            $this->fillFromModel($model);
            $this->daysSoftLocked = PackageSubscription::query()
                ->where('meal_package_id', $model->id)
                ->where('status', PackageSubscription::STATUS_ACTIVE)
                ->exists();
            $this->canManage = $this->canPublish || ($role === 'operation' && $model->status === MealPackage::STATUS_DRAFT);
        } else {
            $this->canManage = in_array($role, ['admin', 'operation'], true);
            abort_unless($this->canManage, 403);
            $this->status = MealPackage::STATUS_DRAFT;
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
            $key = $day->delivery_date->toDateString();
            $this->dayAssignments[$key] = $day->menu_item_id;
            $this->lockedDayAssignments[$key] = $day->menu_item_id;
        }
    }

    public function updatedStartDate(): void
    {
        abort_unless($this->canManage, 403);
        $this->syncEndDate();
        $this->rebuildDayKeys();
    }

    public function updatedDurationDays(): void
    {
        abort_unless($this->canManage, 403);
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

        if ($this->isDayLocked($date)) {
            $this->errorMessage = 'This day is locked because active subscriptions already use it. Swap menus from the subscription page.';

            return;
        }

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

        if ($this->isDayLocked($this->assignDate)) {
            $this->errorMessage = 'This day is locked because active subscriptions already use it.';
            $this->closeAssign();

            return;
        }

        $this->dayAssignments[$this->assignDate] = (int) $this->assignMenuItemId;
        $this->closeAssign();
    }

    public function clearDay(string $date): void
    {
        abort_unless($this->canManage, 403);

        if ($this->isDayLocked($date)) {
            $this->errorMessage = 'This day is locked because active subscriptions already use it.';

            return;
        }

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
            if ($this->isDayLocked($date)) {
                continue;
            }
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
            if ($this->isDayLocked($date)) {
                continue;
            }
            $this->dayAssignments[$date] = (int) $this->bulkMenuItemId;
        }

        $this->errorMessage = '';
    }

    public function clearWeekends(): void
    {
        abort_unless($this->canManage, 403);

        foreach ($this->dayAssignments as $date => $_) {
            if ($this->isDayLocked($date)) {
                continue;
            }
            $dow = Carbon::parse($date)->dayOfWeek;
            if ($dow === Carbon::FRIDAY || $dow === Carbon::SATURDAY) {
                $this->dayAssignments[$date] = null;
            }
        }
    }

    protected function isDayLocked(string $date): bool
    {
        if (! $this->daysSoftLocked) {
            return false;
        }

        return ! empty($this->lockedDayAssignments[$date]);
    }

    public function save(): void
    {
        abort_unless($this->canManage, 403);

        if (! $this->canPublish && $this->status !== MealPackage::STATUS_DRAFT) {
            $this->status = MealPackage::STATUS_DRAFT;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string|max:2000',
            'price_per_day' => 'nullable|integer|min:0',
            'diet_tag' => 'required|in:'.implode(',', MealPackage::DIET_TAGS),
            'duration_days' => 'required|integer|min:1|max:60',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'display_order' => 'integer',
            'status' => 'required|in:draft,published,archived',
            'thumbnail' => 'nullable|image|max:2048',
        ]);

        if (! $this->canPublish && $this->status !== MealPackage::STATUS_DRAFT) {
            $this->errorMessage = 'Only admins can publish or archive packages.';

            return;
        }

        $this->syncEndDate();
        $this->rebuildDayKeys();

        if ($this->daysSoftLocked) {
            foreach ($this->lockedDayAssignments as $date => $menuItemId) {
                if (! empty($menuItemId) && (int) ($this->dayAssignments[$date] ?? 0) !== (int) $menuItemId) {
                    $this->errorMessage = 'Active subscriptions lock existing day menus. Use subscription day swap instead.';

                    return;
                }
            }
        }

        // Rate plans may publish without a pre-built calendar; corporates pick menus at checkout
        // and operations assigns exact dates afterward.

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

            if ($package->status === MealPackage::STATUS_PUBLISHED) {
                MealPackage::query()
                    ->where('id', '!=', $package->id)
                    ->where('status', MealPackage::STATUS_PUBLISHED)
                    ->update([
                        'status' => MealPackage::STATUS_ARCHIVED,
                        'updated_by' => Auth::id(),
                    ]);
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

            $this->lockedDayAssignments = collect($this->dayAssignments)
                ->filter()
                ->all();
        });

        $this->successMessage = 'Package saved.';
        $this->errorMessage = '';
        $this->dispatch('package-updated');
    }

    public function indexRoute(): string
    {
        return Auth::user()?->role?->name === 'admin'
            ? route('admin.packages.index')
            : route('operation.packages.index');
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
