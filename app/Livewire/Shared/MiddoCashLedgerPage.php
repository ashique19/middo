<?php

namespace App\Livewire\Shared;

use App\Models\MiddoCashLedgerEntry;
use App\Support\MiddoCashLedger;
use Livewire\Component;
use Livewire\WithPagination;

class MiddoCashLedgerPage extends Component
{
    use WithPagination;

    public function render()
    {
        $entries = MiddoCashLedgerEntry::query()
            ->with('createdByUser')
            ->orderByDesc('id')
            ->paginate(30);

        return view('livewire.shared.middo-cash-ledger-page', [
            'entries' => $entries,
            'balance' => MiddoCashLedger::balance(),
        ])->layout('layouts.private.app', ['title' => 'Middo cash']);
    }
}
