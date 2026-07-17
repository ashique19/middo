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
        $this->boxes = MiddoBox::query()
            ->where('held_by_user_id', Auth::id())
            ->where('asset_status', 'active')
            ->orderBy('qr_code_id')
            ->get(['id', 'qr_code_id', 'box_model_type'])
            ->map(fn (MiddoBox $box) => [
                'id' => $box->id,
                'qr_code_id' => $box->qr_code_id,
                'box_model_type' => $box->box_model_type,
            ])
            ->all();

        $this->showModal = true;
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
