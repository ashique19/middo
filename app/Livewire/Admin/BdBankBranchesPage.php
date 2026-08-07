<?php

namespace App\Livewire\Admin;

use App\Models\BdBank;
use App\Models\BdBankBranch;
use App\Models\BdBankCity;
use App\Support\BdBanks;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BdBankBranchesPage extends Component
{
    public ?int $filterBankId = null;

    public string $search = '';

    public string $newBankName = '';

    public string $newCityName = '';

    public string $newBranchName = '';

    public ?int $newBranchCityId = null;

    public ?int $editingBranchId = null;

    public string $editingBranchName = '';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role?->name === 'admin', 403);

        $first = BdBank::query()->orderBy('name')->value('id');
        $this->filterBankId = $first ? (int) $first : null;
    }

    public function updatedFilterBankId(): void
    {
        $this->newBranchCityId = null;
        $this->cancelEditBranch();
        $this->clearMessages();
    }

    public function createBank(): void
    {
        $this->clearMessages();
        $name = trim($this->newBankName);
        if ($name === '') {
            $this->errorMessage = 'Bank name is required.';

            return;
        }

        if (BdBank::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            $this->errorMessage = 'That bank already exists.';

            return;
        }

        $bank = BdBank::query()->create([
            'name' => $name,
            'is_active' => true,
        ]);
        BdBanks::forgetCache();
        $this->newBankName = '';
        $this->filterBankId = $bank->id;
        $this->statusMessage = "Bank “{$name}” created.";
    }

    public function createCity(): void
    {
        $this->clearMessages();
        if (! $this->filterBankId) {
            $this->errorMessage = 'Select a bank first.';

            return;
        }

        $name = trim($this->newCityName);
        if ($name === '') {
            $this->errorMessage = 'City name is required.';

            return;
        }

        $exists = BdBankCity::query()
            ->where('bd_bank_id', $this->filterBankId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();
        if ($exists) {
            $this->errorMessage = 'That city already exists for this bank.';

            return;
        }

        $city = BdBankCity::query()->create([
            'bd_bank_id' => $this->filterBankId,
            'name' => $name,
        ]);
        BdBanks::forgetCache();
        $this->newCityName = '';
        $this->newBranchCityId = $city->id;
        $this->statusMessage = "City “{$name}” added.";
    }

    public function createBranch(): void
    {
        $this->clearMessages();

        $name = trim($this->newBranchName);
        if (! $this->newBranchCityId) {
            $this->errorMessage = 'Select a city for the branch.';

            return;
        }
        if ($name === '') {
            $this->errorMessage = 'Branch name is required.';

            return;
        }

        $city = BdBankCity::query()->find($this->newBranchCityId);
        if (! $city || (int) $city->bd_bank_id !== (int) $this->filterBankId) {
            $this->errorMessage = 'City does not belong to the selected bank.';

            return;
        }

        $exists = BdBankBranch::query()
            ->where('bd_bank_city_id', $city->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();
        if ($exists) {
            $this->errorMessage = 'That branch already exists in this city.';

            return;
        }

        BdBankBranch::query()->create([
            'bd_bank_city_id' => $city->id,
            'name' => $name,
            'is_active' => true,
        ]);
        BdBanks::forgetCache();
        $this->newBranchName = '';
        $this->statusMessage = "Branch “{$name}” added.";
    }

    public function startEditBranch(int $branchId): void
    {
        $branch = BdBankBranch::query()->with('city')->findOrFail($branchId);
        $this->editingBranchId = $branch->id;
        $this->editingBranchName = $branch->name;
        $this->clearMessages();
    }

    public function cancelEditBranch(): void
    {
        $this->editingBranchId = null;
        $this->editingBranchName = '';
    }

    public function saveBranch(): void
    {
        $this->clearMessages();
        if (! $this->editingBranchId) {
            return;
        }

        $name = trim($this->editingBranchName);
        if ($name === '') {
            $this->errorMessage = 'Branch name is required.';

            return;
        }

        $branch = BdBankBranch::query()->findOrFail($this->editingBranchId);
        $exists = BdBankBranch::query()
            ->where('bd_bank_city_id', $branch->bd_bank_city_id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->where('id', '!=', $branch->id)
            ->exists();
        if ($exists) {
            $this->errorMessage = 'Another branch already uses that name in this city.';

            return;
        }

        $branch->update(['name' => $name]);
        BdBanks::forgetCache();
        $this->cancelEditBranch();
        $this->statusMessage = 'Branch updated.';
    }

    public function toggleBranchActive(int $branchId): void
    {
        $this->clearMessages();
        $branch = BdBankBranch::query()->findOrFail($branchId);
        $branch->update(['is_active' => ! $branch->is_active]);
        BdBanks::forgetCache();
        $this->statusMessage = $branch->is_active ? 'Branch activated.' : 'Branch deactivated.';
    }

    public function deleteBranch(int $branchId): void
    {
        $this->clearMessages();
        $branch = BdBankBranch::query()->findOrFail($branchId);
        $label = $branch->name;
        $branch->delete();
        BdBanks::forgetCache();
        if ($this->editingBranchId === $branchId) {
            $this->cancelEditBranch();
        }
        $this->statusMessage = "Branch “{$label}” deleted.";
    }

    protected function clearMessages(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
    }

    public function render()
    {
        $banks = BdBank::query()->orderBy('name')->get(['id', 'name', 'is_active']);

        $allCitiesForBank = collect();
        $cities = collect();
        if ($this->filterBankId) {
            $allCitiesForBank = BdBankCity::query()
                ->where('bd_bank_id', $this->filterBankId)
                ->orderBy('name')
                ->get(['id', 'name']);

            $query = BdBankCity::query()
                ->where('bd_bank_id', $this->filterBankId)
                ->with(['branches' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name');

            $search = trim($this->search);
            if ($search !== '') {
                $like = '%'.$search.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhereHas('branches', fn ($b) => $b->where('name', 'like', $like));
                });
            }

            $cities = $query->get();
        }

        return view('livewire.admin.bd-bank-branches-page', [
            'banks' => $banks,
            'allCitiesForBank' => $allCitiesForBank,
            'cities' => $cities,
        ])->layout('layouts.private.app', ['title' => 'Payout bank branches']);
    }
}
