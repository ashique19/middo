<?php

namespace App\Livewire\Admin;

use App\Models\MiddoBankAccount;
use App\Models\MiddoBankLedgerEntry;
use App\Support\MiddoSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class BankAccountsPage extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $bank_name = '';

    public string $account_number = '';

    public string $branch = '';

    public bool $is_default = false;

    public bool $is_active = true;

    public string $notes = '';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $account = MiddoBankAccount::query()->findOrFail($id);
        $this->editingId = $account->id;
        $this->name = $account->name;
        $this->bank_name = $account->bank_name;
        $this->account_number = (string) ($account->account_number ?? '');
        $this->branch = (string) ($account->branch ?? '');
        $this->is_default = (bool) $account->is_default;
        $this->is_active = (bool) $account->is_active;
        $this->notes = (string) ($account->notes ?? '');
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
            'bank_name' => 'required|string|min:2|max:120',
            'account_number' => 'nullable|string|max:64',
            'branch' => 'nullable|string|max:120',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::transaction(function () use ($data) {
                $actorId = (int) Auth::id();
                if ($this->editingId) {
                    $account = MiddoBankAccount::query()->lockForUpdate()->findOrFail($this->editingId);
                    $account->update([
                        ...$data,
                        'updated_by' => $actorId,
                    ]);
                } else {
                    $account = MiddoBankAccount::query()->create([
                        ...$data,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);
                }

                if ($account->is_default) {
                    MiddoBankAccount::query()
                        ->whereKeyNot($account->id)
                        ->update(['is_default' => false]);
                    MiddoSettings::set(MiddoSettings::KEY_DEFAULT_EPS_BANK_ACCOUNT_ID, (string) $account->id);
                }
            });

            $this->statusMessage = $this->editingId ? 'Bank account updated.' : 'Bank account created.';
            $this->closeForm();
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not save bank account.';
        }
    }

    public function deleteAccount(int $id): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';

        $account = MiddoBankAccount::query()->findOrFail($id);
        if (MiddoBankLedgerEntry::query()->where('middo_bank_account_id', $id)->exists()) {
            $this->errorMessage = 'Cannot delete an account with ledger entries. Deactivate it instead.';

            return;
        }

        $account->delete();
        if (MiddoSettings::defaultEpsBankAccountId() === $id) {
            MiddoSettings::set(MiddoSettings::KEY_DEFAULT_EPS_BANK_ACCOUNT_ID, null);
        }
        $this->statusMessage = 'Bank account deleted.';
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $account = MiddoBankAccount::query()->findOrFail($id);
        $account->update([
            'is_active' => ! $account->is_active,
            'updated_by' => Auth::id(),
        ]);
        $this->statusMessage = $account->is_active ? 'Account activated.' : 'Account deactivated.';
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->bank_name = '';
        $this->account_number = '';
        $this->branch = '';
        $this->is_default = false;
        $this->is_active = true;
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $accounts = MiddoBankAccount::query()
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('bank_name')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.admin.bank-accounts-page', [
            'accounts' => $accounts,
        ])->layout('layouts.private.app', ['title' => 'Bank accounts']);
    }
}
