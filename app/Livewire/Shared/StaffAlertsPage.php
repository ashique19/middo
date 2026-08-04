<?php

namespace App\Livewire\Shared;

use App\Models\StaffAlert;
use App\Support\StaffAlerts;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class StaffAlertsPage extends Component
{
    use WithPagination;

    public string $statusMessage = '';

    public function mount(): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['kitchen', 'admin', 'operation', 'delivery'], true), 403);
    }

    public function markRead(int $id): void
    {
        StaffAlerts::markRead($id, (int) Auth::id());
        $this->statusMessage = 'Alert marked as read.';
    }

    public function markAllRead(): void
    {
        $count = StaffAlerts::markAllRead((int) Auth::id());
        $this->statusMessage = $count > 0
            ? "Marked {$count} alert(s) as read."
            : 'No unread alerts.';
        $this->resetPage();
    }

    public function render()
    {
        $userId = (int) Auth::id();
        $role = Auth::user()?->role?->name;

        $alerts = StaffAlert::query()
            ->with('orderGroup.menuItem')
            ->where('user_id', $userId)
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->paginate(20);

        $title = match ($role) {
            'kitchen' => 'Kitchen alerts',
            'delivery' => 'Rider alerts',
            default => 'Ops alerts',
        };
        $layout = match ($role) {
            'kitchen' => 'kitchen.layout.app',
            'delivery' => 'delivery.layout.app',
            default => 'layouts.private.app',
        };

        return view('livewire.shared.staff-alerts-page', [
            'alerts' => $alerts,
            'unreadCount' => StaffAlerts::unreadCount($userId),
        ])->layout($layout, ['title' => $title]);
    }
}
