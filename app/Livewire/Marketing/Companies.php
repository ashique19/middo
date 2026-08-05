<?php

namespace App\Livewire\Marketing;

use App\Models\Area;
use App\Models\City;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Companies extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showCreate = false;

    public string $name = '';

    public string $address = '';

    public ?int $cityId = null;

    public ?int $areaId = null;

    public string $hrName = '';

    public string $hrMobile = '';

    public string $notes = '';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role?->name === 'ground_marketing', 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCityId($value): void
    {
        $this->areaId = null;
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->showCreate = true;
        $this->statusMessage = '';
        $this->errorMessage = '';
    }

    public function createCompany(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'name' => 'required|string|min:2|max:160',
            'address' => 'required|string|min:8|max:255',
            'cityId' => 'required|exists:cities,id',
            'areaId' => 'required|exists:areas,id',
            'hrName' => 'nullable|string|max:120',
            'hrMobile' => ['nullable', 'regex:/^01[3-9]\d{8}$/'],
            'notes' => 'nullable|string|max:2000',
        ]);

        $company = Company::query()->create([
            'name' => trim($this->name),
            'address' => trim($this->address),
            'city_id' => $this->cityId,
            'area_id' => $this->areaId,
            'hr_name' => $this->hrName ?: null,
            'hr_mobile' => $this->hrMobile ?: null,
            'status' => Company::STATUS_LEAD,
            'notes' => $this->notes ?: null,
            'created_by' => Auth::id(),
        ]);

        $this->reset(['name', 'address', 'cityId', 'areaId', 'hrName', 'hrMobile', 'notes', 'showCreate']);
        $this->statusMessage = "Company “{$company->name}” created as lead.";

        $this->redirect(route('marketing.companies.show', $company), navigate: true);
    }

    public function render()
    {
        $companies = Company::query()
            ->with(['city', 'area'])
            ->where('created_by', Auth::id())
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('hr_name', 'like', $term)
                        ->orWhere('hr_mobile', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(15);

        $areas = $this->cityId
            ? Area::query()->where('city_id', $this->cityId)->orderBy('name')->get()
            : collect();

        return view('livewire.marketing.companies', [
            'companies' => $companies,
            'cities' => City::query()->orderBy('name')->get(),
            'areas' => $areas,
        ])->layout('layouts.private.app', ['title' => 'Companies']);
    }
}
