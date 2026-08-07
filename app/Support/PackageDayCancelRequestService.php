<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PackageDayCancelRequest;
use App\Models\PackageSubscription;
use App\Models\PackageSubscriptionEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PackageDayCancelRequestService
{
    /**
     * Corporate requests cancel for a confirmed pending package day.
     */
    public function request(User $corporate, Order $order, string $reason): PackageDayCancelRequest
    {
        $corporate->loadMissing('role');
        if (($corporate->role?->name ?? null) !== 'corporate') {
            throw new RuntimeException('Only corporate accounts can request package day cancels.');
        }

        if ((int) $order->user_id !== (int) $corporate->id) {
            throw new RuntimeException('Order not found.');
        }

        if (! $order->package_subscription_id) {
            throw new RuntimeException('This order is not part of a meal package.');
        }

        if ($order->order_status !== 'pending') {
            throw new RuntimeException('Only pending package days can be requested for cancel.');
        }

        if (! OrderCutoff::allowsModification($order)) {
            throw new RuntimeException(OrderCutoff::modificationDeniedMessage());
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Enter a reason for the cancel request.');
        }

        if (mb_strlen($reason) > 500) {
            throw new RuntimeException('Reason must be 500 characters or fewer.');
        }

        $existing = PackageDayCancelRequest::query()
            ->where('order_id', $order->id)
            ->pending()
            ->exists();

        if ($existing) {
            throw new RuntimeException('A cancel request is already pending for this day.');
        }

        return DB::transaction(function () use ($corporate, $order, $reason) {
            $request = PackageDayCancelRequest::create([
                'package_subscription_id' => $order->package_subscription_id,
                'order_id' => $order->id,
                'delivery_date' => $order->delivery_date->toDateString(),
                'status' => PackageDayCancelRequest::STATUS_PENDING,
                'reason' => $reason,
                'requested_by' => $corporate->id,
            ]);

            PackageSubscriptionEvent::create([
                'package_subscription_id' => $order->package_subscription_id,
                'type' => PackageSubscriptionEvent::TYPE_DAY_CANCEL_REQUESTED,
                'summary' => 'Corporate requested cancel for order #'.$order->id.' ('.$order->delivery_date->format('M d').').',
                'meta' => [
                    'request_id' => $request->id,
                    'order_id' => $order->id,
                    'delivery_date' => $order->delivery_date->toDateString(),
                    'reason' => $reason,
                ],
                'created_by' => $corporate->id,
            ]);

            return $request;
        });
    }

    public function withdraw(User $corporate, PackageDayCancelRequest $request): PackageDayCancelRequest
    {
        $sub = PackageSubscription::query()->find($request->package_subscription_id);
        if (! $sub || (int) $sub->user_id !== (int) $corporate->id) {
            throw new RuntimeException('Cancel request not found.');
        }

        if (! $request->isPending()) {
            throw new RuntimeException('Only pending cancel requests can be withdrawn.');
        }

        $request->update([
            'status' => PackageDayCancelRequest::STATUS_WITHDRAWN,
            'reviewed_at' => now(),
        ]);

        PackageSubscriptionEvent::create([
            'package_subscription_id' => $request->package_subscription_id,
            'type' => PackageSubscriptionEvent::TYPE_DAY_CANCEL_REQUEST_WITHDRAWN,
            'summary' => 'Corporate withdrew cancel request for order #'.$request->order_id.'.',
            'meta' => [
                'request_id' => $request->id,
                'order_id' => $request->order_id,
                'delivery_date' => optional($request->delivery_date)->toDateString(),
            ],
            'created_by' => $corporate->id,
        ]);

        return $request->fresh();
    }

    /**
     * @return array{request: PackageDayCancelRequest, refunded_amount: int}
     */
    public function approve(User $staff, PackageDayCancelRequest $request, ?string $opsNote = null): array
    {
        $this->assertStaff($staff);

        if (! $request->isPending()) {
            throw new RuntimeException('This cancel request is no longer pending.');
        }

        $order = Order::query()->findOrFail($request->order_id);
        $note = trim((string) $opsNote);
        $reason = $request->reason;
        if ($note !== '') {
            $reason .= ' [Ops: '.$note.']';
        }

        if ($note !== '') {
            $request->update(['ops_note' => $note]);
        }

        $result = app(PackageSubscriptionService::class)->skipDayAsStaff($staff, $order, $reason);

        $request->refresh();
        if ($request->isPending()) {
            $request->update([
                'status' => PackageDayCancelRequest::STATUS_APPROVED,
                'reviewed_by' => $staff->id,
                'reviewed_at' => now(),
                'refunded_amount' => (int) $result['refunded_amount'],
            ]);
        }

        PackageSubscriptionEvent::create([
            'package_subscription_id' => $request->package_subscription_id,
            'type' => PackageSubscriptionEvent::TYPE_DAY_CANCEL_REQUEST_APPROVED,
            'summary' => 'Ops approved cancel request for order #'.$request->order_id.' — refunded Tk '.number_format((int) $result['refunded_amount']).'.',
            'meta' => [
                'request_id' => $request->id,
                'order_id' => $request->order_id,
                'delivery_date' => optional($request->delivery_date)->toDateString(),
                'refunded_amount' => (int) $result['refunded_amount'],
                'ops_note' => $note !== '' ? $note : null,
            ],
            'created_by' => $staff->id,
        ]);

        return [
            'request' => $request->fresh(),
            'refunded_amount' => (int) $result['refunded_amount'],
        ];
    }

    public function reject(User $staff, PackageDayCancelRequest $request, ?string $opsNote = null): PackageDayCancelRequest
    {
        $this->assertStaff($staff);

        if (! $request->isPending()) {
            throw new RuntimeException('This cancel request is no longer pending.');
        }

        $note = trim((string) $opsNote);

        $request->update([
            'status' => PackageDayCancelRequest::STATUS_REJECTED,
            'ops_note' => $note !== '' ? $note : null,
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
        ]);

        PackageSubscriptionEvent::create([
            'package_subscription_id' => $request->package_subscription_id,
            'type' => PackageSubscriptionEvent::TYPE_DAY_CANCEL_REQUEST_REJECTED,
            'summary' => 'Ops rejected cancel request for order #'.$request->order_id.'.',
            'meta' => [
                'request_id' => $request->id,
                'order_id' => $request->order_id,
                'delivery_date' => optional($request->delivery_date)->toDateString(),
                'ops_note' => $note !== '' ? $note : null,
                'reason' => $request->reason,
            ],
            'created_by' => $staff->id,
        ]);

        return $request->fresh();
    }

    /**
     * When ops cancels a day directly, close any open corporate request for that order.
     */
    public function markApprovedForOrder(Order $order, User $staff, int $refundedAmount): void
    {
        PackageDayCancelRequest::query()
            ->where('order_id', $order->id)
            ->pending()
            ->get()
            ->each(function (PackageDayCancelRequest $request) use ($staff, $refundedAmount) {
                $request->update([
                    'status' => PackageDayCancelRequest::STATUS_APPROVED,
                    'reviewed_by' => $staff->id,
                    'reviewed_at' => now(),
                    'refunded_amount' => $refundedAmount,
                ]);
            });
    }

    protected function assertStaff(User $actor): void
    {
        $actor->loadMissing('role');
        if (! in_array($actor->role?->name, ['admin', 'operation'], true)) {
            throw new RuntimeException('Only admin or operation staff can review cancel requests.');
        }
    }
}
