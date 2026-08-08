<?php

namespace App\Livewire\Admin;

use App\Models\SettingChangeLog;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class SettingsAuditPage extends Component
{
    use WithPagination;

    public function render()
    {
        $logs = Schema::hasTable('setting_change_logs')
            ? SettingChangeLog::query()
                ->with('actor')
                ->latest('id')
                ->paginate(30)
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 30);

        return view('livewire.admin.settings-audit-page', [
            'logs' => $logs,
        ])->layout('layouts.private.app', ['title' => 'Settings audit log']);
    }
}
