<?php

namespace App\Livewire\Corporate;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class TrackOrderModal extends Component
{
    public bool $showModal = false;

    public ?int $orderId = null;

    public array $order = [];

    public array $logs = [];

    #[On('open-track-order-modal')]
    public function openModal($orderId): void
    {
        $id = is_array($orderId) ? ($orderId['orderId'] ?? null) : $orderId;

        if (! $id) {
            return;
        }

        $order = Order::with('menuItem')
            ->where('id', (int) $id)
            ->where('user_id', Auth::id())
            ->first();

        if (! $order) {
            return;
        }

        $this->orderId = $order->id;
        $this->order = $order->toArray();
        $this->logs = $order->logs()
            ->with('performedBy:id,first_name,last_name')
            ->latest()
            ->get()
            ->map(fn ($log) => [
                ...$log->toArray(),
                'performer_name' => $log->performedBy?->name,
            ])
            ->all();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->orderId = null;
        $this->order = [];
        $this->logs = [];
    }

    public function logLabel(string $event): string
    {
        return \App\Support\OrderAudit::label($event);
    }

    public function logDescription(array $log): string
    {
        return \App\Support\OrderAudit::description($log);
    }

    public function render()
    {
        return view('livewire.corporate.track-order-modal');
    }
}
