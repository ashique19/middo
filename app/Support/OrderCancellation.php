<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corporate cancel of a pending à-la-carte (or any pending) order before cutoff.
 *
 * Single source of truth for web (DeleteOrderModal) and mobile API.
 * Refunds amount_paid only — never unpaid residual or discount-adjusted “list” total.
 */
class OrderCancellation
{
    /**
     * @return array{order: Order, refunded_amount: int}
     */
    public static function cancelPendingOwnedBy(User $actor, int $orderId): array
    {
        return DB::transaction(function () use ($actor, $orderId) {
            /** @var Order|null $order */
            $order = Order::query()
                ->whereKey($orderId)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw ValidationException::withMessages([
                    'order' => ['Order not found.'],
                ]);
            }

            if ($order->order_status !== 'pending') {
                throw ValidationException::withMessages([
                    'order' => ['Only pending orders can be cancelled.'],
                ]);
            }

            if (! OrderCutoff::allowsModification($order)) {
                throw ValidationException::withMessages([
                    'order' => [OrderCutoff::modificationDeniedMessage()],
                ]);
            }

            $refund = (int) ($order->amount_paid ?? 0);

            if ($refund > 0) {
                WalletLedger::credit(
                    $actor,
                    $refund,
                    WalletTransaction::TYPE_REFUND,
                    'Refund for cancelled order #'.$order->id,
                    $order
                );
            }

            OrderTransition::apply($order, OrderTransition::CANCELLED, [
                'updated_by' => $actor->id,
            ]);

            return [
                'order' => $order->fresh(['menuItem']),
                'refunded_amount' => $refund,
            ];
        });
    }

    /**
     * Ops/admin cancel on the corporate lens — same pending+cutoff rules, refunds the buyer.
     *
     * @return array{order: Order, refunded_amount: int}
     */
    public static function cancelPendingAsStaff(User $staff, Order $order): array
    {
        $role = $staff->role?->name ?? $staff->loadMissing('role')->role?->name;
        if (! OrderLens::isStaff($role)) {
            throw ValidationException::withMessages([
                'order' => ['Only staff can cancel on behalf of corporate.'],
            ]);
        }

        return DB::transaction(function () use ($staff, $order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->order_status !== 'pending') {
                throw ValidationException::withMessages([
                    'order' => ['Only pending orders can be cancelled on the corporate lens.'],
                ]);
            }

            if (! OrderCutoff::allowsModification($locked)) {
                throw ValidationException::withMessages([
                    'order' => [OrderCutoff::modificationDeniedMessage()],
                ]);
            }

            $refund = (int) ($locked->amount_paid ?? 0);
            $corporate = User::query()->whereKey($locked->user_id)->lockForUpdate()->first();

            if ($refund > 0 && $corporate) {
                WalletLedger::credit(
                    $corporate,
                    $refund,
                    WalletTransaction::TYPE_REFUND,
                    'Ops refund for cancelled order #'.$locked->id,
                    $locked
                );
            }

            OrderTransition::apply($locked, OrderTransition::CANCELLED, [
                'updated_by' => $staff->id,
            ]);

            OrderLogWrite::opsIntervene($locked, $staff, 'cancel_pending', [
                'lens' => OrderLens::CORPORATE,
                'refunded_amount' => $refund,
            ]);

            return [
                'order' => $locked->fresh(['menuItem']),
                'refunded_amount' => $refund,
            ];
        });
    }
}
