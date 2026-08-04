<?php

namespace App\Livewire\Kitchen;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
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

        $kitchenId = Auth::id();

        try {
            $qr = DB::transaction(function () use ($boxId, $kitchenId) {
                $box = MiddoBox::query()
                    ->whereKey($boxId)
                    ->lockForUpdate()
                    ->first();

                if (! $box || ! $box->isIncomingToKitchen($kitchenId)) {
                    throw new \RuntimeException('This box is not incoming to your kitchen.');
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

        $boxes = MiddoBox::query()
            ->with(['heldByUser', 'logs' => fn ($q) => $q->latest('id')->limit(1)])
            ->incomingToKitchen($kitchenId)
            ->orderBy('qr_code_id')
            ->paginate(20);

        return view('livewire.kitchen.incoming-boxes', [
            'boxes' => $boxes,
        ])->layout('layouts.private.app', ['title' => 'Incoming Middo Boxes']);
    }
}
