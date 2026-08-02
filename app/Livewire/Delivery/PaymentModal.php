<?php

namespace App\Livewire\Delivery;

use App\Models\Order;
use App\Models\User;
use App\Support\MimSms;
use App\Support\OrderTransition;
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

    public int $amountPaid = 0;

    public int $amountDue = 0;

    public string $accountHolderName = '';

    public string $accountHolderMobile = '';

    public string $receiverName = '';

    public string $customerName = '';

    public string $customerMobile = '';

    public bool $hasSeparateReceiver = false;

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

        if ($order->isPaid() || $order->amountDue() <= 0) {
            return;
        }

        $party = $order->partyPayload();

        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->paymentMethod = '';
        $this->orderId = $order->id;
        $this->orderLabel = '#'.$order->id;
        $this->menuName = $order->menuItem?->name ?? 'Order';
        $this->quantity = (int) $order->quantity;
        $this->totalAmount = (int) $order->total_amount;
        $this->amountPaid = $order->amountPaidValue();
        $this->amountDue = $order->amountDue();
        $this->accountHolderName = $party['account_holder_name'];
        $this->accountHolderMobile = (string) ($party['account_holder_mobile'] ?? '');
        $this->receiverName = $party['receiver_name'];
        $this->customerName = $party['customer_name'];
        $this->customerMobile = (string) ($party['receiver_mobile'] ?? '');
        $this->hasSeparateReceiver = (bool) $party['has_separate_receiver'];
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

                $due = $order->amountDue();
                if ($due <= 0) {
                    throw new \RuntimeException('Nothing due for this order.');
                }

                OrderTransition::apply($order, OrderTransition::DELIVERED_AND_PAID, [
                    'payment_status' => 'paid',
                    'amount_paid' => $order->netTotalAmount(),
                    'cash_collected' => $due,
                    'updated_by' => $riderId,
                ]);

                User::query()->whereKey($riderId)->lockForUpdate()->increment('balance', $due);
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

        $due = $order->amountDue();
        $paymentUrl = URL::temporarySignedRoute(
            'public.order-payment',
            now()->addDays(3),
            ['order' => $order->id]
        );

        $message = "Middo payment for order #{$order->id} ({$this->menuName}): ৳{$due} due. Pay here: {$paymentUrl}";

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
