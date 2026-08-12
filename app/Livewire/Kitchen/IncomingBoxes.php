<?php

namespace App\Livewire\Kitchen;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use App\Models\User;
use App\Support\KitchenBoxRequestFlow;
use App\Support\MiddoBoxLifecycle;
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

        try {
            $qr = $this->receiveOneBox($boxId, (int) Auth::id());
            $this->statusMessage = "Received {$qr} into kitchen inventory.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not receive this box.';
        }
    }

    public function receiveAllBoxes(array $boxIds = []): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $ids = collect($boxIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $kitchenId = (int) Auth::id();
        $received = 0;
        $errors = [];

        foreach ($ids as $boxId) {
            try {
                $this->receiveOneBox($boxId, $kitchenId);
                $received++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage() ?: 'Could not receive a box.';
            }
        }

        if ($received > 0) {
            $this->statusMessage = "Received {$received} ".str('box')->plural($received).' into kitchen inventory.';
            $this->resetPage();
        }
        if ($errors !== []) {
            $this->errorMessage = $errors[0];
        }
    }

    protected function receiveOneBox(int $boxId, int $kitchenId): string
    {
        return DB::transaction(function () use ($boxId, $kitchenId) {
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
                'notes' => 'Received at '.(MiddoBoxLifecycle::partyLabel(User::query()->find($kitchenId)) ?: 'kitchen'),
                'performed_by' => $kitchenId,
            ]);

            KitchenBoxRequestFlow::markReceivedAtKitchen($box, $kitchenId);

            return $box->qr_code_id;
        });
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
            ->with(['heldByUser', 'requestBox', 'logs' => fn ($q) => $q->latest('id')->limit(1)])
            ->incomingToKitchen($kitchenId)
            ->whereIn('id', $visibleBoxIds)
            ->orderBy('qr_code_id')
            ->paginate(20);

        $nodes = collect($boxes->items())
            ->map(function (MiddoBox $box) {
                $latestAction = $box->logs->first()?->log_action;
                $canReceive = in_array($latestAction, self::RECEIVE_ACTIONS, true);
                $isRiderReturn = $latestAction === 'returned_to_kitchen';
                $requestId = $box->requestBox?->kitchen_box_request_id
                    ? (int) $box->requestBox->kitchen_box_request_id
                    : null;
                $holderId = $box->held_by_user_id ? (int) $box->held_by_user_id : 0;

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

                $groupKey = $requestId
                    ? 'ops-kitchen-'.$requestId
                    : ($isRiderReturn
                        ? 'rider-return-'.$holderId
                        : ($holderId > 0 ? 'held-'.$holderId.'-'.$sourceLabel : 'solo-'.$box->id));

                $groupTitle = $requestId
                    ? 'Ops→kitchen run #'.$requestId
                    : ($isRiderReturn ? 'Rider return' : $sourceLabel);

                $heldBy = $box->heldByUser?->name ?? '—';
                $heldByMobile = $box->heldByUser?->mobile;

                return [
                    'id' => $box->id,
                    'qr_code_id' => $box->qr_code_id,
                    'model' => str($box->box_model_type)->headline()->toString(),
                    'source_label' => $sourceLabel,
                    'status_label' => $statusLabel,
                    'held_by' => $heldBy,
                    'held_by_mobile' => $heldByMobile,
                    'can_receive' => $canReceive,
                    'run_group_key' => $groupKey,
                    'run_group_title' => $groupTitle,
                    'receive_confirm_label' => $canReceive
                        ? $this->receiveConfirmLabel(1, $heldBy, $heldByMobile)
                        : null,
                ];
            });

        $runGroups = $nodes
            ->groupBy('run_group_key')
            ->map(function ($groupNodes, $key) {
                $first = $groupNodes->first();
                $receiveIds = $groupNodes
                    ->filter(fn (array $n) => $n['can_receive'])
                    ->pluck('id')
                    ->values()
                    ->all();

                return [
                    'key' => $key,
                    'title' => $first['run_group_title'],
                    'held_by' => $first['held_by'],
                    'held_by_mobile' => $first['held_by_mobile'],
                    'box_count' => $groupNodes->count(),
                    'receive_all_ids' => $receiveIds,
                    'receive_confirm_label' => $this->receiveConfirmLabel(
                        count($receiveIds),
                        $first['held_by'],
                        $first['held_by_mobile'],
                    ),
                    'nodes' => $groupNodes->values()->all(),
                ];
            })
            ->values()
            ->all();

        return view('livewire.kitchen.incoming-boxes', [
            'boxes' => $boxes,
            'nodes' => $nodes->values()->all(),
            'runGroups' => $runGroups,
        ])->layout('kitchen.layout.app', ['title' => 'Incoming Middo Boxes']);
    }

    protected function receiveConfirmLabel(int $count, ?string $name, ?string $mobile): string
    {
        if ($count < 1) {
            return '';
        }

        $fromBits = collect([$name && $name !== '—' ? $name : null, $mobile])
            ->map(fn ($v) => is_string($v) ? trim($v) : '')
            ->filter()
            ->values()
            ->all();

        $fromLabel = $fromBits === [] ? 'rider' : implode(', ', $fromBits);

        return 'Confirm receive '.$count.' '.str('box')->plural($count).' from '.$fromLabel.'?';
    }
}
