<?php

namespace App\Livewire\Kitchen;

use App\Models\OrderComplaint;
use App\Support\KitchenComplaints;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ComplaintShow extends Component
{
    public OrderComplaint $complaint;

    public function mount(OrderComplaint $complaint): void
    {
        $kitchenId = (int) Auth::id();
        $root = $complaint->parent_id
            ? OrderComplaint::query()->findOrFail($complaint->parent_id)
            : $complaint;

        abort_unless(KitchenComplaints::belongsToKitchen($root, $kitchenId), 403);

        $this->complaint = $root->load(['order.menuItem', 'order.user', 'order.orderGroup']);
    }

    public function render()
    {
        $messages = $this->complaint->threadMessages();

        return view('livewire.kitchen.complaint-show', [
            'messages' => $messages,
            'order' => $this->complaint->order,
        ])->layout('kitchen.layout.app', ['title' => 'Complaint #'.$this->complaint->id]);
    }
}
