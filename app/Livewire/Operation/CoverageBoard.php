<?php

namespace App\Livewire\Operation;

use App\Support\OpsAreaCoverage;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CoverageBoard extends Component
{
    public string $deliveryDate = '';

    public function mount(): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);
        $this->deliveryDate = now('Asia/Dhaka')->toDateString();
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function render()
    {
        $date = $this->deliveryDate !== ''
            ? $this->deliveryDate
            : now('Asia/Dhaka')->toDateString();

        $rows = OpsAreaCoverage::rows($date);
        $gaps = collect($rows)->where('gap', true)->count();
        $demandOrders = collect($rows)->sum('orders');
        $demandQty = collect($rows)->sum('quantity');

        return view('livewire.operation.coverage-board', [
            'rows' => $rows,
            'gaps' => $gaps,
            'demandOrders' => $demandOrders,
            'demandQty' => $demandQty,
            'rolePrefix' => $this->rolePrefix(),
        ])->layout('layouts.private.app', ['title' => 'Coverage']);
    }
}
