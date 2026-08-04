<?php

namespace App\Livewire\Operation;

use App\Support\OpsSlaBoard;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SlaBoard extends Component
{
    public string $tab = 'unassigned';

    public function mount(): void
    {
        $role = Auth::user()?->role?->name;
        abort_unless(in_array($role, ['admin', 'operation'], true), 403);
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function render()
    {
        $counts = OpsSlaBoard::counts();
        $unassigned = OpsSlaBoard::unassignedGroups();
        $late = OpsSlaBoard::lateToPack();

        return view('livewire.operation.sla-board', [
            'counts' => $counts,
            'unassigned' => $unassigned,
            'late' => $late,
            'rolePrefix' => $this->rolePrefix(),
        ])->layout('layouts.private.app', ['title' => 'Dispatch SLA']);
    }
}
