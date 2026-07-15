<?php

namespace App\Livewire\Kitchen;

use App\Models\MiddoBox;
use App\Models\MiddoBoxLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class BoxesAtKitchen extends Component
{
    use WithPagination;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function sendToWarehouse(int $boxId): void
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

                if (! $box || ! $box->isAtKitchen($kitchenId)) {
                    throw new \RuntimeException('This box is not in your kitchen inventory.');
                }

                if ($box->orderMiddoBoxes()->exists()) {
                    throw new \RuntimeException('This box is reserved for a dispatched order.');
                }

                $box->update([
                    'kitchen_id' => null,
                    'held_by_user_id' => null,
                    'asset_status' => 'at_middo_warehouse',
                    'last_scanned_at' => now(),
                ]);

                MiddoBoxLog::create([
                    'middo_box_id' => $box->id,
                    'custody_status' => 'warehouse',
                    'log_action' => 'returned_to_warehouse',
                ]);

                return $box->qr_code_id;
            });

            $this->statusMessage = "{$qr} sent to Middo warehouse.";
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not send box to warehouse.';
        }
    }

    public function render()
    {
        $kitchenId = Auth::id();

        $boxes = MiddoBox::query()
            ->atKitchen($kitchenId)
            ->orderBy('qr_code_id')
            ->paginate(20);

        return view('livewire.kitchen.boxes-at-kitchen', [
            'boxes' => $boxes,
        ])->layout('layouts.private.app', ['title' => 'Boxes at Kitchen']);
    }
}
