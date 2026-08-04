<?php

namespace App\Livewire\Shared;

use App\Support\CodDueRecon;
use App\Support\OrderCutoff;
use App\Support\StaffPortal;
use Livewire\Component;

class CodDueReconPage extends Component
{
    public string $date = '';

    public function mount(): void
    {
        abort_unless(StaffPortal::canAccessMoney(), 403);
        $this->date = now(OrderCutoff::timezone())->toDateString();
    }

    public function render()
    {
        $report = CodDueRecon::forDate($this->date);

        return view('livewire.shared.cod-due-recon', [
            'report' => $report,
            'routePrefix' => StaffPortal::rolePrefix(),
        ])->layout('layouts.private.app', ['title' => 'COD / Due recon']);
    }
}
