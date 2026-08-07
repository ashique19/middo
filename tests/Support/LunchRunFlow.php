<?php

namespace Tests\Support;

use App\Livewire\Delivery\KitchenDispatches;
use App\Livewire\Kitchen\DispatchOrderModal;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderTransition;
use Livewire\Livewire;

/**
 * Shared lunch-run steps for the claim-before-dispatch lifecycle.
 */
class LunchRunFlow
{
    /**
     * ready → rider_assigned (claim). Order must already be ready.
     */
    public static function riderAccept(User $rider, Order $order): void
    {
        Livewire::actingAs($rider)
            ->test(KitchenDispatches::class)
            ->call('acceptOrder', $order->id)
            ->assertSet('errorMessage', null);
    }

    /**
     * rider_assigned → packed (kitchen confirms rider + boxes).
     *
     * @param  list<MiddoBox>|MiddoBox  $boxes
     */
    public static function kitchenDispatch(User $kitchen, Order $order, MiddoBox|array $boxes): void
    {
        $boxes = is_array($boxes) ? $boxes : [$boxes];
        $test = Livewire::actingAs($kitchen)
            ->test(DispatchOrderModal::class)
            ->call('openModal', $order->id);

        foreach ($boxes as $box) {
            $test->call('toggleBox', $box->id);
        }

        $test->call('dispatchOrder')->assertSet('showModal', false);
    }

    /**
     * packed → on_the_way (pickup).
     */
    public static function riderPickUp(User $rider, Order $order): void
    {
        Livewire::actingAs($rider)
            ->test(KitchenDispatches::class)
            ->call('pickUpOrder', $order->id)
            ->assertSet('errorMessage', null);
    }

    /**
     * Full path from ready: claim → pack → pickup.
     *
     * @param  list<MiddoBox>|MiddoBox  $boxes
     */
    public static function fromReadyToOnTheWay(User $kitchen, User $rider, Order $order, MiddoBox|array $boxes): Order
    {
        if ($order->order_status === OrderTransition::PROCESSING) {
            OrderTransition::apply($order->fresh(), OrderTransition::READY);
            $order->refresh();
        }

        if ($order->order_status === OrderTransition::READY) {
            self::riderAccept($rider, $order);
            $order->refresh();
        }

        if ($order->order_status === OrderTransition::RIDER_ASSIGNED) {
            self::kitchenDispatch($kitchen, $order, $boxes);
            $order->refresh();
        }

        if ($order->order_status === OrderTransition::PACKED) {
            self::riderPickUp($rider, $order);
            $order->refresh();
        }

        return $order->fresh();
    }
}
