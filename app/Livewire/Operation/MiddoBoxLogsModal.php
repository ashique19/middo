<?php

namespace App\Livewire\Operation;

use App\Models\MiddoBox;
use Livewire\Attributes\On;
use Livewire\Component;

class MiddoBoxLogsModal extends Component
{
    public bool $showModal = false;

    public ?int $boxId = null;

    public string $boxQrCode = '';

    public array $logs = [];

    #[On('open-middo-box-logs-modal')]
    public function openModal($boxId = null): void
    {
        $id = is_array($boxId)
            ? ($boxId['boxId'] ?? null)
            : $boxId;

        if (! $id) {
            return;
        }

        $box = MiddoBox::query()
            ->with(['logs' => fn ($query) => $query->latest()])
            ->find((int) $id);

        if (! $box) {
            return;
        }

        $this->boxId = $box->id;
        $this->boxQrCode = $box->qr_code_id;
        $this->logs = $box->logs
            ->map(fn ($log) => [
                'id' => $log->id,
                'order_id' => $log->order_id,
                'custody_status' => $log->custody_status,
                'log_action' => $log->log_action,
                'created_at' => $log->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i'),
            ])
            ->all();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->boxId = null;
        $this->boxQrCode = '';
        $this->logs = [];
    }

    public function render()
    {
        return view('livewire.operation.middo-box-logs-modal');
    }
}
