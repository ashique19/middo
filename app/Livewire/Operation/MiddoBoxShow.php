<?php

namespace App\Livewire\Operation;

use App\Models\MiddoBox;
use App\Support\MiddoBoxLifecycle;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MiddoBoxShow extends Component
{
    public MiddoBox $middoBox;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public ?int $unitCostBdt = null;

    public function mount(MiddoBox $middoBox): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);
        $this->middoBox = $middoBox;
        $this->unitCostBdt = $middoBox->unit_cost_bdt;
    }

    public function saveUnitCost(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'unitCostBdt' => 'nullable|integer|min:0|max:10000000',
        ]);

        $this->middoBox->update([
            'unit_cost_bdt' => $this->unitCostBdt !== null ? max(0, (int) $this->unitCostBdt) : null,
        ]);
        $this->middoBox->refresh();
        $this->unitCostBdt = $this->middoBox->unit_cost_bdt;
        $this->statusMessage = 'Unit cost saved.';
    }

    public function render()
    {
        $box = $this->middoBox->fresh(['heldByUser', 'kitchen']);
        $metrics = MiddoBoxLifecycle::metrics($box);
        $tree = MiddoBoxLifecycle::trackingTree($box);

        return view('livewire.operation.middo-box-show', [
            'box' => $box,
            'metrics' => $metrics,
            'tree' => $tree,
        ])->layout('layouts.private.app', ['title' => 'Box '.$box->qr_code_id]);
    }
}
