<?php

namespace App\Livewire\Shared;

use App\Support\CodDueRecon;
use App\Support\OrderCutoff;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CodDueReconPage extends Component
{
    public string $date = '';

    public function mount(): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);
        $this->date = now(OrderCutoff::timezone())->toDateString();
    }

    public function render()
    {
        $report = CodDueRecon::forDate($this->date);
        $prefix = Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';

        return view('livewire.shared.cod-due-recon', [
            'report' => $report,
            'routePrefix' => $prefix,
        ])->layout('layouts.private.app', ['title' => 'COD / Due recon']);
    }
}
