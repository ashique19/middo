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
        return match ($event) {
            'created' => 'Order Placed',
            'order_status_changed' => 'Status Updated',
            'payment_status_changed' => 'Payment Updated',
            'quantity_changed' => 'Quantity Updated',
            'deleted' => 'Order Deleted',
            default => 'Order Updated',
        };
    }

    public function logDescription(array $log): string
    {
        $metadata = $log['metadata'] ?? [];
        $event = $log['event'];

        return match ($event) {
            'created' => sprintf(
                'Order placed for %d meal%s.',
                $metadata['snapshot']['quantity'] ?? 1,
                ($metadata['snapshot']['quantity'] ?? 1) === 1 ? '' : 's'
            ),
            'order_status_changed' => sprintf(
                'Status changed from %s to %s.',
                ucfirst(str_replace('_', ' ', $metadata['changes']['order_status']['from'] ?? 'unknown')),
                ucfirst(str_replace('_', ' ', $metadata['changes']['order_status']['to'] ?? 'unknown')),
            ),
            'payment_status_changed' => sprintf(
                'Payment changed from %s to %s.',
                ucfirst(str_replace('_', ' ', $metadata['changes']['payment_status']['from'] ?? 'unknown')),
                ucfirst(str_replace('_', ' ', $metadata['changes']['payment_status']['to'] ?? 'unknown')),
            ).(isset($metadata['changes']['amount_paid']['to'])
                ? sprintf(' Amount paid is now ৳%s.', number_format((int) $metadata['changes']['amount_paid']['to']))
                : ''),
            'quantity_changed' => sprintf(
                'Quantity changed from %d to %d.',
                $metadata['changes']['quantity']['from'] ?? 0,
                $metadata['changes']['quantity']['to'] ?? 0,
            ),
            'deleted' => 'Order was removed from your schedule.',
            default => 'Order details were updated.',
        };
    }

    public function render()
    {
        return view('livewire.corporate.track-order-modal');
    }
}
