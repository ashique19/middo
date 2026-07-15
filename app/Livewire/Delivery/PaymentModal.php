<?php

namespace App\Livewire\Delivery;

use App\Models\Order;
use App\Models\User;
use App\Support\MimSms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\On;
use Livewire\Component;

class PaymentModal extends Component
{
    public bool $showModal = false;

    public ?int $orderId = null;

    public string $orderLabel = '';

    public string $menuName = '';

    public int $quantity = 0;

    public int $totalAmount = 0;

    public string $customerName = '';

    public string $customerMobile = '';

    public string $paymentMethod = '';

    public string $receiverPhone = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    #[On('open-delivery-payment-modal')]
    public function openModal($orderId = null): void
    {
        $id = is_array($orderId) ? ($orderId['orderId'] ?? null) : $orderId;

        if (! $id) {
            return;
        }

        $riderId = Auth::id();
        $order = Order::with(['menuItem', 'user'])->find((int) $id);

        if (! $order || (int) $order->delivery_rider_id !== (int) $riderId || ! $order->isDelivered()) {
            return;
        }

        if ($order->isPaid()) {
            return;
        }

        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->paymentMethod = '';
        $this->orderId = $order->id;
        $this->orderLabel = '#'.$order->id;
        $this->menuName = $order->menuItem?->name ?? 'Order';
        $this->quantity = (int) $order->quantity;
        $this->totalAmount = (int) $order->total_amount;
        $this->customerName = trim(($order->user?->first_name ?? '').' '.($order->user?->last_name ?? '')) ?: 'Customer';
        $this->customerMobile = $order->user?->mobile ?? '';
        $this->receiverPhone = $this->customerMobile;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->orderId = null;
        $this->paymentMethod = '';
        $this->receiverPhone = '';
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    public function selectCash(): void
    {
        $this->paymentMethod = 'cash';
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    public function selectOnline(): void
    {
        $this->paymentMethod = 'online';
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    public function confirmCashPayment(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (! $this->orderId) {
            return;
        }

        $riderId = Auth::id();

        try {
            DB::transaction(function () use ($riderId) {
                $order = Order::query()->whereKey($this->orderId)->lockForUpdate()->first();

                if (! $order || (int) $order->delivery_rider_id !== (int) $riderId || ! $order->isDelivered()) {
                    throw new \RuntimeException('Order is not available for cash payment.');
                }

                if ($order->isPaid()) {
                    throw new \RuntimeException('This order is already paid.');
                }

                $order->update([
                    'order_status' => 'delivered_and_paid',
                    'payment_status' => 'paid',
                    'updated_by' => $riderId,
                ]);

                User::query()->whereKey($riderId)->lockForUpdate()->increment('balance', (int) $order->total_amount);
            });

            $this->dispatch('order-payment-recorded', message: "Cash payment recorded for {$this->orderLabel}. Rider balance updated.");
            $this->closeModal();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not record cash payment.';
        }
    }

    public function sendOnlinePaymentLink(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $this->validate([
            'receiverPhone' => ['required', 'regex:/^01[3-9]\d{8}$/'],
        ], [
            'receiverPhone.required' => 'Receiver phone number is required.',
            'receiverPhone.regex' => 'Enter a valid 11-digit BD mobile number (e.g. 01710123456).',
        ]);

        if (! $this->orderId) {
            return;
        }

        $riderId = Auth::id();
        $order = Order::with('menuItem')->find($this->orderId);

        if (! $order || (int) $order->delivery_rider_id !== (int) $riderId || ! $order->isDelivered() || $order->isPaid()) {
            $this->errorMessage = 'Order is not available for online payment.';

            return;
        }

        $paymentUrl = URL::temporarySignedRoute(
            'public.order-payment',
            now()->addDays(3),
            ['order' => $order->id]
        );

        $message = "Middo payment for order #{$order->id} ({$this->menuName}): ৳{$this->totalAmount}. Pay here: {$paymentUrl}";

        $sent = MimSms::send($this->receiverPhone, $message);

        if (! $sent && ! config('app.debug')) {
            $this->errorMessage = 'Could not send payment SMS. Please try again.';

            return;
        }

        $this->successMessage = config('app.debug') && ! $sent
            ? 'Payment link prepared (SMS skipped in debug/unavailable). Link was generated for the customer.'
            : 'Payment link sent to '.$this->receiverPhone.'.';

        $order->update([
            'updated_by' => $riderId,
        ]);
    }

    public function render()
    {
        return view('livewire.delivery.payment-modal');
    }
}
