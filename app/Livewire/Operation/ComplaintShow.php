<?php

namespace App\Livewire\Operation;

use App\Models\OrderComplaint;
use App\Support\StaffOrderRoutes;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ComplaintShow extends Component
{
    public OrderComplaint $complaint;

    public string $replyMessage = '';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(OrderComplaint $complaint): void
    {
        abort_unless(in_array(Auth::user()?->role?->name, ['admin', 'operation'], true), 403);

        $root = $complaint->parent_id
            ? OrderComplaint::query()->findOrFail($complaint->parent_id)
            : $complaint;

        $this->complaint = $root->load([
            'order.menuItem',
            'order.user',
            'order.orderGroup.kitchen',
        ]);
    }

    public function reply(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        if ($this->complaint->isResolved()) {
            $this->errorMessage = 'This complaint is marked complete. Replies are closed.';

            return;
        }

        $this->validate([
            'replyMessage' => 'required|string|min:5|max:2000',
        ]);

        OrderComplaint::create([
            'order_id' => $this->complaint->order_id,
            'parent_id' => $this->complaint->id,
            'is_reply' => true,
            'status' => OrderComplaint::STATUS_OPEN,
            'category' => $this->complaint->category,
            'message' => $this->replyMessage,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->replyMessage = '';
        $this->statusMessage = 'Reply posted.';
        $this->complaint->refresh();
    }

    public function markComplete(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        if ($this->complaint->isResolved()) {
            $this->statusMessage = 'Already marked complete.';

            return;
        }

        $this->complaint->markResolved(Auth::id());
        $this->complaint->refresh();
        $this->statusMessage = 'Complaint marked complete.';
    }

    protected function rolePrefix(): string
    {
        return Auth::user()?->role?->name === 'admin' ? 'admin' : 'operation';
    }

    public function render()
    {
        return view('livewire.operation.complaint-show', [
            'messages' => $this->complaint->threadMessages(),
            'order' => $this->complaint->order,
            'rolePrefix' => $this->rolePrefix(),
            'orderShowUrl' => StaffOrderRoutes::show($this->complaint->order_id, 'corporate'),
        ])->layout('layouts.private.app', [
            'title' => 'Complaint #'.$this->complaint->id,
        ]);
    }
}
