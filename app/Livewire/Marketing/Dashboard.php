<?php

namespace App\Livewire\Marketing;

use App\Models\Company;
use App\Models\CompanyAppointment;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->role?->name === 'ground_marketing', 403);
    }

    public function render()
    {
        $marketerId = (int) Auth::id();

        $stats = [
            'companies' => Company::query()->where('created_by', $marketerId)->count(),
            'leads' => Company::query()->where('created_by', $marketerId)->where('status', Company::STATUS_LEAD)->count(),
            'upcoming' => CompanyAppointment::query()
                ->where('created_by', $marketerId)
                ->where('status', CompanyAppointment::STATUS_SCHEDULED)
                ->where('scheduled_at', '>=', now())
                ->count(),
            'active' => Company::query()->where('created_by', $marketerId)->where('status', Company::STATUS_ACTIVE)->count(),
        ];

        $upcoming = CompanyAppointment::query()
            ->with('company')
            ->where('created_by', $marketerId)
            ->where('status', CompanyAppointment::STATUS_SCHEDULED)
            ->where('scheduled_at', '>=', now()->subDay())
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get();

        return view('livewire.marketing.dashboard', [
            'stats' => $stats,
            'upcoming' => $upcoming,
        ])->layout('layouts.private.app', ['title' => 'Ground Marketing']);
    }
}
