<?php

namespace App\Livewire\Shared;

use App\Models\Area;
use App\Models\City;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class AreasAdmin extends Component
{
    public string $newCityName = '';

    public string $newAreaName = '';

    public ?int $newAreaCityId = null;

    public ?int $editingCityId = null;

    public string $editingCityName = '';

    public ?int $editingAreaId = null;

    public string $editingAreaName = '';

    public ?int $editingAreaCityId = null;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);
    }

    public function createCity(): void
    {
        $this->clearMessages();

        $name = trim($this->newCityName);
        if ($name === '') {
            $this->errorMessage = 'City name is required.';

            return;
        }

        if (City::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            $this->errorMessage = 'A city with that name already exists.';

            return;
        }

        City::create(['name' => $name]);
        $this->newCityName = '';
        $this->statusMessage = "City “{$name}” created.";
    }

    public function startEditCity(int $cityId): void
    {
        $city = City::query()->findOrFail($cityId);
        $this->editingCityId = $city->id;
        $this->editingCityName = $city->name;
        $this->clearMessages();
    }

    public function cancelEditCity(): void
    {
        $this->editingCityId = null;
        $this->editingCityName = '';
    }

    public function saveCity(): void
    {
        $this->clearMessages();

        if (! $this->editingCityId) {
            return;
        }

        $name = trim($this->editingCityName);
        if ($name === '') {
            $this->errorMessage = 'City name is required.';

            return;
        }

        $exists = City::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->where('id', '!=', $this->editingCityId)
            ->exists();
        if ($exists) {
            $this->errorMessage = 'A city with that name already exists.';

            return;
        }

        City::query()->whereKey($this->editingCityId)->update(['name' => $name]);
        $this->cancelEditCity();
        $this->statusMessage = 'City updated.';
    }

    public function deleteCity(int $cityId): void
    {
        $this->clearMessages();

        $city = City::query()->withCount('areas')->findOrFail($cityId);
        if ($city->areas_count > 0) {
            $this->errorMessage = 'Remove or reassign areas before deleting this city.';

            return;
        }

        if (User::query()->where('city_id', $cityId)->exists()) {
            $this->errorMessage = 'City is still linked to users.';

            return;
        }

        $city->delete();
        $this->statusMessage = "City “{$city->name}” deleted.";
    }

    public function createArea(): void
    {
        $this->clearMessages();

        $name = trim($this->newAreaName);
        if ($name === '') {
            $this->errorMessage = 'Area name is required.';

            return;
        }

        if (! $this->newAreaCityId) {
            $this->errorMessage = 'Choose a city for the area.';

            return;
        }

        if (! City::query()->whereKey($this->newAreaCityId)->exists()) {
            $this->errorMessage = 'Selected city was not found.';

            return;
        }

        $dup = Area::query()
            ->where('city_id', $this->newAreaCityId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();
        if ($dup) {
            $this->errorMessage = 'That area already exists in this city.';

            return;
        }

        Area::create([
            'name' => $name,
            'city_id' => $this->newAreaCityId,
        ]);
        $this->newAreaName = '';
        $this->statusMessage = "Area “{$name}” created.";
    }

    public function startEditArea(int $areaId): void
    {
        $area = Area::query()->findOrFail($areaId);
        $this->editingAreaId = $area->id;
        $this->editingAreaName = $area->name;
        $this->editingAreaCityId = $area->city_id;
        $this->clearMessages();
    }

    public function cancelEditArea(): void
    {
        $this->editingAreaId = null;
        $this->editingAreaName = '';
        $this->editingAreaCityId = null;
    }

    public function saveArea(): void
    {
        $this->clearMessages();

        if (! $this->editingAreaId) {
            return;
        }

        $name = trim($this->editingAreaName);
        if ($name === '' || ! $this->editingAreaCityId) {
            $this->errorMessage = 'Area name and city are required.';

            return;
        }

        $dup = Area::query()
            ->where('city_id', $this->editingAreaCityId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->where('id', '!=', $this->editingAreaId)
            ->exists();
        if ($dup) {
            $this->errorMessage = 'That area already exists in this city.';

            return;
        }

        Area::query()->whereKey($this->editingAreaId)->update([
            'name' => $name,
            'city_id' => $this->editingAreaCityId,
        ]);
        $this->cancelEditArea();
        $this->statusMessage = 'Area updated.';
    }

    public function deleteArea(int $areaId): void
    {
        $this->clearMessages();

        $area = Area::query()->findOrFail($areaId);

        if (User::query()->where('area_id', $areaId)->exists()) {
            $this->errorMessage = 'Area is still the primary location for one or more users.';

            return;
        }

        if (Order::query()->where('area_id', $areaId)->exists()) {
            $this->errorMessage = 'Area is referenced by orders and cannot be deleted.';

            return;
        }

        if (Schema::hasTable('area_user') && DB::table('area_user')->where('area_id', $areaId)->exists()) {
            $this->errorMessage = 'Detach riders from this area before deleting.';

            return;
        }

        $area->delete();
        $this->statusMessage = "Area “{$area->name}” deleted.";
    }

    protected function clearMessages(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
    }

    public function render()
    {
        $cities = City::query()->with(['areas' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get();

        return view('livewire.shared.areas-admin', [
            'cities' => $cities,
        ])->layout('layouts.private.app', ['title' => 'Areas & cities']);
    }
}
