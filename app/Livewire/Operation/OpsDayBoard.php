<?php

namespace App\Livewire\Operation;

use App\Support\OpsDayChecklist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

class OpsDayBoard extends Component
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

        $report = OpsDayChecklist::forDate($date);
        $prefix = $this->rolePrefix();

        $deepLinks = [];
        foreach ($report['sections'] as $section) {
            $name = $prefix.'.'.$section['route_suffix'];
            if (Route::has($name)) {
                $deepLinks[$section['id']] = route($name);
            } elseif ($section['route_suffix'] === 'middo-boxes.index' && Route::has('operation.middo-boxes.index')) {
                $deepLinks[$section['id']] = route('operation.middo-boxes.index');
            } else {
                $deepLinks[$section['id']] = null;
            }
        }

        return view('livewire.operation.ops-day-board', [
            'report' => $report,
            'rolePrefix' => $prefix,
            'deepLinks' => $deepLinks,
        ])->layout('layouts.private.app', ['title' => 'Ops day']);
    }
}
