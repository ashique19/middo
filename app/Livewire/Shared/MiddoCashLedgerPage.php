<?php

namespace App\Livewire\Shared;

use App\Models\MiddoCashLedgerEntry;
use App\Support\MiddoCashLedger;
use Livewire\Component;
use Livewire\WithPagination;

class MiddoCashLedgerPage extends Component
{
    use WithPagination;

    /** all|package */
    public string $entryFilter = 'all';

    public function updatingEntryFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $entries = MiddoCashLedgerEntry::query()
            ->with('createdByUser')
            ->when($this->entryFilter === 'package', function ($query) {
                $query->where(function ($q) {
                    $q->where('description', 'like', '%Package%')
                        ->orWhere('description', 'like', '%package%');
                });
            })
            ->orderByDesc('id')
            ->paginate(30);

        return view('livewire.shared.middo-cash-ledger-page', [
            'entries' => $entries,
            'balance' => MiddoCashLedger::balance(),
        ])->layout('layouts.private.app', ['title' => 'Middo cash']);
    }
}
