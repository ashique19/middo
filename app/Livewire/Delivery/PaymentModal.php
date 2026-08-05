<?php

namespace App\Livewire\Delivery;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\PartnerPayable;
use App\Models\User;
use App\Support\MimSms;
use App\Support\OrderMoneyFlow;
use App\Support\OrderPaymentMethod;
use App\Support\OrderTransition;
use App\Support\RiderCommission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    /** Cash the rider is recording this pass (1..amountDue). */
    public int $cashCollectAmount = 0;

    public int $commissionAmount = 0;

    public int $dueToMiddo = 0;

    public int $openCommission = 0;

    public string $shortReason = '';

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
        $rider = Auth::user();
        $due = $order->amountDue();
        $openCommission = $this->openDeliveryCommissionFor($order, $rider);

        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->paymentMethod = '';
        $this->shortReason = '';
        $this->orderId = $order->id;
        $this->orderLabel = '#'.$order->id;
        $this->menuName = $order->menuItem?->name ?? 'Order';
        $this->quantity = (int) $order->quantity;
        $this->totalAmount = (int) $order->total_amount;
        $this->amountPaid = $order->amountPaidValue();
        $this->amountDue = $due;
        $this->cashCollectAmount = $due;
        $this->openCommission = $openCommission;
        $this->recomputeCashSplit();
        $this->accountHolderName = $party['account_holder_name'];
        $this->accountHolderMobile = (string) ($party['account_holder_mobile'] ?? '');
        $this->receiverName = $party['receiver_name'];
        $this->customerName = $party['customer_name'];
        $this->customerMobile = (string) ($party['receiver_mobile'] ?? '');
        $this->hasSeparateReceiver = (bool) $party['has_separate_receiver'];
        $this->receiverPhone = $this->customerMobile;
        $this->showModal = true;
    }

    public function updatedCashCollectAmount(mixed $value): void
    {
        $this->cashCollectAmount = max(0, (int) $value);
        $this->recomputeCashSplit();
    }

    protected function recomputeCashSplit(): void
    {
        $cash = min(max(0, $this->cashCollectAmount), max(0, $this->amountDue));
        $this->cashCollectAmount = $cash;
        $this->commissionAmount = min($this->openCommission, $cash);
        $this->dueToMiddo = max(0, $cash - $this->commissionAmount);
    }

    protected function openDeliveryCommissionFor(Order $order, User $rider): int
    {
        if (Schema::hasTable('partner_payables')) {
            $open = (int) PartnerPayable::query()
                ->where('order_id', $order->id)
                ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
                ->where('beneficiary_user_id', $rider->id)
                ->where('status', PartnerPayable::STATUS_OPEN)
                ->value('amount');

            if ($open > 0) {
                return $open;
            }

            // Already settled/voided — nothing left to keep in-kind.
            $exists = PartnerPayable::query()
                ->where('order_id', $order->id)
                ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
                ->where('beneficiary_user_id', $rider->id)
                ->exists();

            if ($exists) {
                return 0;
            }
        }

        return min(RiderCommission::forLunchOrder($rider, $order), $order->amountDue());
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->orderId = null;
        $this->paymentMethod = '';
        $this->receiverPhone = '';
        $this->cashCollectAmount = 0;
        $this->commissionAmount = 0;
        $this->dueToMiddo = 0;
        $this->openCommission = 0;
        $this->shortReason = '';
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    public function selectCash(): void
    {
        $this->paymentMethod = 'cash';
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->recomputeCashSplit();
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

        $this->recomputeCashSplit();
        $cashAmount = (int) $this->cashCollectAmount;

        if ($cashAmount < 1) {
            $this->errorMessage = 'Enter a cash amount of at least ৳1.';

            return;
        }

        if ($cashAmount > $this->amountDue) {
            $this->errorMessage = 'Cash cannot exceed the amount due (৳'.$this->amountDue.').';

            return;
        }

        $riderId = Auth::id();
        $isShort = $cashAmount < $this->amountDue;

        if ($isShort && trim($this->shortReason) === '') {
            $this->errorMessage = 'Add a short reason when collecting less than the full amount due.';

            return;
        }

        try {
            $dueToMiddo = 0;
            $residual = 0;
            $fullyPaid = false;

            DB::transaction(function () use ($riderId, $cashAmount, $isShort, &$dueToMiddo, &$residual, &$fullyPaid) {
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

                if ($cashAmount < 1 || $cashAmount > $due) {
                    throw new \RuntimeException('Cash amount must be between ৳1 and ৳'.$due.'.');
                }

                $priorDue = $order->cash_due_to_middo !== null
                    ? max(0, (int) $order->cash_due_to_middo)
                    : 0;

                $newPaid = $order->amountPaidValue() + $cashAmount;
                $newCollected = (int) ($order->cash_collected ?? 0) + $cashAmount;
                $fullyPaid = $newPaid >= $order->netTotalAmount();
                $residual = max(0, $order->netTotalAmount() - $newPaid);

                $attrs = [
                    'payment_status' => $fullyPaid ? 'paid' : 'pending',
                    'amount_paid' => min($newPaid, $order->netTotalAmount()),
                    'cash_collected' => $newCollected,
                    'payment_method' => OrderPaymentMethod::CASH_ON_DELIVERY,
                    'updated_by' => $riderId,
                ];

                if ($fullyPaid) {
                    OrderTransition::apply($order, OrderTransition::DELIVERED_AND_PAID, $attrs);
                } else {
                    // Stay delivered with residual customer debt — reopen PaymentModal later.
                    $order->update($attrs);
                }

                $rider = User::query()->whereKey($riderId)->lockForUpdate()->firstOrFail();
                $rider->increment('balance', $cashAmount);

                $commission = OrderMoneyFlow::settleDeliveryCommissionFromCash(
                    $order->fresh(),
                    $rider->fresh(),
                    $cashAmount
                );

                $dueThis = max(0, $cashAmount - $commission);
                $dueToMiddo = $priorDue + $dueThis;
                $order->forceFill(['cash_due_to_middo' => $dueToMiddo])->saveQuietly();

                if ($commission > 0) {
                    User::query()->whereKey($riderId)->lockForUpdate()->decrement('balance', $commission);
                }

                if ($isShort && Schema::hasTable('order_logs')) {
                    OrderLog::create([
                        'order_id' => $order->id,
                        'event' => 'cash_short_collect',
                        'performed_by' => $riderId,
                        'metadata' => [
                            'cash_collected_this_pass' => $cashAmount,
                            'amount_due_before' => $due,
                            'residual_customer_due' => $residual,
                            'commission_settled' => $commission,
                            'due_to_middo_this_pass' => $dueThis,
                            'reason' => trim($this->shortReason),
                        ],
                    ]);
                }
            });

            $message = $fullyPaid
                ? "Cash recorded for {$this->orderLabel}. Due to Middo ৳{$dueToMiddo}."
                : "Short cash ৳{$cashAmount} recorded for {$this->orderLabel}. Residual customer due ৳{$residual}. Due to Middo so far ৳{$dueToMiddo}.";

            $this->dispatch('order-payment-recorded', message: $message);
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
