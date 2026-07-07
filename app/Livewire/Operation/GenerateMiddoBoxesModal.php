<?php

namespace App\Livewire\Operation;

use App\Models\MiddoBox;
use Livewire\Component;

class GenerateMiddoBoxesModal extends Component
{
    public bool $showModal = false;

    public int $quantity = 1;

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
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->quantity = 1;
        $this->resetErrorBag();
    }

    public function generate(): void
    {
        $this->validate();

        MiddoBox::generateBatch($this->quantity);

        $this->closeModal();
        $this->dispatch('middo-boxes-generated');
    }

    public function render()
    {
        return view('livewire.operation.generate-middo-boxes-modal');
    }
}
