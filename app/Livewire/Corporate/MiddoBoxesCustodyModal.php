<?php

namespace App\Livewire\Corporate;

use App\Models\MiddoBox;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class MiddoBoxesCustodyModal extends Component
{
    public bool $showModal = false;

    public array $boxes = [];

    #[On('open-middo-boxes-custody-modal')]
    public function openModal(): void
    {
        $this->loadBoxes();
        $this->showModal = true;
    }

    protected function loadBoxes(): void
    {
        $this->boxes = MiddoBox::query()
            ->where('held_by_user_id', Auth::id())
            ->where('asset_status', 'active')
            ->orderBy('qr_code_id')
            ->get(['id', 'qr_code_id', 'box_model_type', 'ready_for_pickup', 'ready_for_pickup_at'])
            ->map(fn (MiddoBox $box) => [
                'id' => $box->id,
                'qr_code_id' => $box->qr_code_id,
                'box_model_type' => $box->box_model_type,
                'ready_for_pickup' => (bool) $box->ready_for_pickup,
            ])
            ->all();
    }

    public function markReadyForPickup(int $boxId): void
    {
        $box = MiddoBox::query()
            ->where('id', $boxId)
            ->where('held_by_user_id', Auth::id())
            ->where('asset_status', 'active')
            ->first();

        if (! $box) {
            return;
        }

        $box->update([
            'ready_for_pickup' => true,
            'ready_for_pickup_at' => now(),
        ]);

        $this->loadBoxes();
        $this->dispatch('box-marked-ready', boxId: $boxId);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        return view('livewire.corporate.middo-boxes-custody-modal');
    }
}
