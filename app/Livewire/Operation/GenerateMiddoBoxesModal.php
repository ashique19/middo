<?php

namespace App\Livewire\Operation;

use App\Models\MiddoBox;
use Livewire\Component;

class GenerateMiddoBoxesModal extends Component
{
    public bool $showModal = false;

    public int $quantity = 1;

    /** @var list<array{id:int,qr:string}> */
    public array $generatedBoxes = [];

    protected function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:1|max:500',
        ];
    }

    public function openModal(): void
    {
        $this->resetErrorBag();
        $this->quantity = 1;
        $this->generatedBoxes = [];
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->quantity = 1;
        $this->generatedBoxes = [];
        $this->resetErrorBag();
    }

    public function generate(): void
    {
        $this->validate();

        $created = MiddoBox::generateBatch($this->quantity);
        $this->generatedBoxes = $created
            ->map(fn (MiddoBox $box) => [
                'id' => $box->id,
                'qr' => $box->qr_code_id,
            ])
            ->values()
            ->all();

        $this->dispatch('middo-boxes-generated', count: count($this->generatedBoxes));
    }

    public function done(): void
    {
        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.operation.generate-middo-boxes-modal');
    }
}
