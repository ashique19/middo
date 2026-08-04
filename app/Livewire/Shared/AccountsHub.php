<?php

namespace App\Livewire\Shared;

use App\Models\Order;
use App\Models\OrderMoneyEvent;
use App\Models\PartnerPayable;
use App\Support\MiddoCashLedger;
use App\Support\StaffPortal;
use Livewire\Component;
use Livewire\WithPagination;

class AccountsHub extends Component
{
    use WithPagination;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public string $payableFilter = 'open';

    public function mount(): void
    {
        abort_unless(StaffPortal::canAccessMoney(), 403);
    }

    public function updatingPayableFilter(): void
    {
        $this->resetPage('payablesPage');
    }

    public function settlePayable(int $id): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        try {
            $payable = PartnerPayable::query()->findOrFail($id);
            $prefix = StaffPortal::rolePrefix();

            if ($payable->beneficiary_role === PartnerPayable::ROLE_DELIVERY) {
                throw new \RuntimeException(
                    'Delivery payables are paid via Rider money withdrawals ('.$prefix.'.rider-money), not Accounts Hub settle — so the rider wallet stays correct.'
                );
            }

            if ($payable->beneficiary_role === PartnerPayable::ROLE_KITCHEN) {
                throw new \RuntimeException(
                    'Kitchen payables are paid via Kitchen money withdrawals ('.$prefix.'.kitchen-money), not Accounts Hub settle — single FIFO payout path.'
                );
            }

            throw new \RuntimeException('Unknown payable role; settle via partner money queues.');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not settle payable.';
        }
    }

    public function render()
    {
        $openKitchen = (int) PartnerPayable::query()
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->where('beneficiary_role', PartnerPayable::ROLE_KITCHEN)
            ->sum('amount');

        $openDelivery = (int) PartnerPayable::query()
            ->where('status', PartnerPayable::STATUS_OPEN)
            ->where('beneficiary_role', PartnerPayable::ROLE_DELIVERY)
            ->sum('amount');

        $payables = PartnerPayable::query()
            ->with(['order.menuItem', 'beneficiary'])
            ->when($this->payableFilter !== 'all', fn ($q) => $q->where('status', $this->payableFilter))
            ->latest('id')
            ->paginate(15, ['*'], 'payablesPage');

        $recentEvents = OrderMoneyEvent::query()
            ->with(['order:id,user_id,menu_item_id,delivery_date'])
            ->latest('id')
            ->limit(25)
            ->get();

        $recentOrders = Order::query()
            ->with(['user:id,first_name,last_name,company_name', 'menuItem:id,name'])
            ->where(function ($q) {
                $q->where('kitchen_share_amount', '>', 0)
                    ->orWhere('delivery_share_amount', '>', 0)
                    ->orWhere('amount_paid', '>', 0)
                    ->orWhere('cash_collected', '>', 0);
            })
            ->latest('id')
            ->limit(12)
            ->get();

        $prefix = StaffPortal::rolePrefix();

        return view('livewire.shared.accounts-hub', [
            'middoCash' => MiddoCashLedger::balance(),
            'openKitchen' => $openKitchen,
            'openDelivery' => $openDelivery,
            'payables' => $payables,
            'recentEvents' => $recentEvents,
            'recentOrders' => $recentOrders,
            'orderShowRoutePrefix' => $prefix,
        ])->layout('layouts.private.app', [
            'title' => 'Accounts',
        ]);
    }
}
