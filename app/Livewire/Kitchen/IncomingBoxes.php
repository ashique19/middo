<?php

namespace App\Livewire\Kitchen;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Support\KitchenBoxRequestFlow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class IncomingBoxes extends Component
{
    use WithPagination;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function receiveBox(int $boxId): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $kitchenId = (int) Auth::id();

        try {
            $qr = DB::transaction(function () use ($boxId, $kitchenId) {
                $box = MiddoBox::query()
                    ->whereKey($boxId)
                    ->lockForUpdate()
                    ->first();

                if (! $box || ! $box->isIncomingToKitchen($kitchenId)) {
                    throw new \RuntimeException('This box is not incoming to your kitchen.');
                }

                $latestAction = KitchenBoxRequestFlow::latestBoxAction($box->id);
                $receivable = in_array($latestAction, [
                    'handed_to_kitchen_stock',
                    'returned_to_kitchen',
                    'dispatched_to_kitchen', // legacy immediate-assign path
                ], true);

                if (! $receivable) {
                    throw new \RuntimeException('Wait for the rider to hand this box before confirming receive.');
                }

                $box->update([
                    'held_by_user_id' => $kitchenId,
                    'kitchen_id' => $kitchenId,
                    'asset_status' => 'active',
                    'last_scanned_at' => now(),
                ]);

                MiddoBoxLog::create([
                    'middo_box_id' => $box->id,
                    'custody_status' => 'assigned_at_kitchen',
                    'log_action' => 'received_at_kitchen',
                    'performed_by' => $kitchenId,
                ]);

                KitchenBoxRequestFlow::markReceivedAtKitchen($box, $kitchenId);

                return $box->qr_code_id;
            });

            $this->statusMessage = "Received {$qr} into kitchen inventory.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not receive this box.';
        }
    }

    public function render()
    {
        $kitchenId = Auth::id();

        $latestLogIds = MiddoBoxLog::query()
            ->selectRaw('MAX(id)')
            ->groupBy('middo_box_id');

        $receivableBoxIds = MiddoBoxLog::query()
            ->whereIn('id', $latestLogIds)
            ->whereIn('log_action', [
                'handed_to_kitchen_stock',
                'returned_to_kitchen',
                'dispatched_to_kitchen', // legacy immediate-assign path
            ])
            ->pluck('middo_box_id');

        $boxes = MiddoBox::query()
            ->with(['heldByUser', 'logs' => fn ($q) => $q->latest('id')->limit(1)])
            ->incomingToKitchen($kitchenId)
            ->whereIn('id', $receivableBoxIds)
            ->orderBy('qr_code_id')
            ->paginate(20);

        return view('livewire.kitchen.incoming-boxes', [
            'boxes' => $boxes,
        ])->layout('kitchen.layout.app', ['title' => 'Incoming Middo Boxes']);
    }
}
