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

    /** @var list<string> */
    public const LIST_ACTIONS = [
        'rider_accepted_kitchen_stock', // en route after rider pickup
        'handed_to_kitchen_stock',
        'returned_to_kitchen',
        'dispatched_to_kitchen', // legacy immediate-assign path
    ];

    /** @var list<string> */
    public const RECEIVE_ACTIONS = [
        'handed_to_kitchen_stock',
        'returned_to_kitchen',
        'dispatched_to_kitchen',
    ];

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
                if (! in_array($latestAction, self::RECEIVE_ACTIONS, true)) {
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
            ->selectRaw('MAX(id) as id')
            ->groupBy('middo_box_id')
            ->pluck('id');

        $visibleBoxIds = MiddoBoxLog::query()
            ->whereIn('id', $latestLogIds)
            ->whereIn('log_action', self::LIST_ACTIONS)
            ->pluck('middo_box_id');

        $boxes = MiddoBox::query()
            ->with(['heldByUser', 'logs' => fn ($q) => $q->latest('id')->limit(1)])
            ->incomingToKitchen($kitchenId)
            ->whereIn('id', $visibleBoxIds)
            ->orderBy('qr_code_id')
            ->paginate(20);

        $nodes = collect($boxes->items())
            ->map(function (MiddoBox $box) {
                $latestAction = $box->logs->first()?->log_action;
                $canReceive = in_array($latestAction, self::RECEIVE_ACTIONS, true);
                $sourceLabel = match ($latestAction) {
                    'returned_to_kitchen' => 'Rider return',
                    'handed_to_kitchen_stock', 'dispatched_to_kitchen', 'rider_accepted_kitchen_stock' => 'Warehouse',
                    default => 'Incoming',
                };
                $statusLabel = match ($latestAction) {
                    'rider_accepted_kitchen_stock' => 'On the way',
                    'handed_to_kitchen_stock', 'dispatched_to_kitchen', 'returned_to_kitchen' => 'Ready to receive',
                    default => 'Awaiting',
                };

                return [
                    'id' => $box->id,
                    'qr_code_id' => $box->qr_code_id,
                    'model' => str($box->box_model_type)->headline()->toString(),
                    'source_label' => $sourceLabel,
                    'status_label' => $statusLabel,
                    'held_by' => $box->heldByUser?->name ?? '—',
                    'held_by_mobile' => $box->heldByUser?->mobile,
                    'can_receive' => $canReceive,
                ];
            })
            ->all();

        return view('livewire.kitchen.incoming-boxes', [
            'boxes' => $boxes,
            'nodes' => $nodes,
        ])->layout('kitchen.layout.app', ['title' => 'Incoming Middo Boxes']);
    }
}
