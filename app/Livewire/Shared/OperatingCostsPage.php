<?php

namespace App\Livewire\Shared;

use App\Models\MiddoOperatingCost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class OperatingCostsPage extends Component
{
    use WithPagination;

    public string $runType = 'all';

    public function mount(): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);
    }

    public function updatingRunType(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $costs = MiddoOperatingCost::query()
            ->with(['rider', 'order'])
            ->when($this->runType !== 'all', fn ($q) => $q->where('run_type', $this->runType))
            ->latest('id')
            ->paginate(25);

        $byRunType = MiddoOperatingCost::query()
            ->select('run_type', DB::raw('COUNT(*) as rows'), DB::raw('SUM(amount) as total'))
            ->groupBy('run_type')
            ->orderBy('run_type')
            ->get();

        $total = (int) MiddoOperatingCost::query()->sum('amount');
        $runTypes = MiddoOperatingCost::query()
            ->whereNotNull('run_type')
            ->distinct()
            ->orderBy('run_type')
            ->pluck('run_type');

        return view('livewire.shared.operating-costs', [
            'costs' => $costs,
            'byRunType' => $byRunType,
            'total' => $total,
            'runTypes' => $runTypes,
        ])->layout('layouts.private.app', ['title' => 'Operating costs']);
    }
}
