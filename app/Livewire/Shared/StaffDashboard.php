<?php

namespace App\Livewire\Shared;

use App\Support\OpsDashboardMetrics;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StaffDashboard extends Component
{
    public string $role = 'operation';

    public function mount(): void
    {
        $role = Auth::user()?->role?->name;
        abort_unless(in_array($role, ['admin', 'operation'], true), 403);
        $this->role = $role;
    }

    public function render()
    {
        $data = OpsDashboardMetrics::forRole($this->role);

        return view('livewire.shared.staff-dashboard', [
            'data' => $data,
        ])->layout('layouts.private.app', [
            'title' => $this->role === 'admin' ? 'Admin Dashboard' : 'Operation Dashboard',
        ]);
    }
}
