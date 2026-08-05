<?php

namespace App\Livewire\Corporate;

use App\Models\Order;
use App\Models\OrderComplaint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ComplaintSupportModal extends Component
{
    use WithFileUploads;

    public bool $showModal = false;

    public ?int $orderId = null;

    public array $order = [];

    public array $thread = [];

    public bool $hasExistingComplaint = false;

    public string $category = 'delivery';

    public string $message = '';

    public $attachment = null;

    public string $successMessage = '';

    #[On('open-complaint-support-modal')]
    public function openModal($orderId): void
    {
        $id = is_array($orderId) ? ($orderId['orderId'] ?? null) : $orderId;

        if (! $id) {
            return;
        }

        $order = Order::with('menuItem')
            ->where('id', (int) $id)
            ->where('user_id', Auth::id())
            ->first();

        if (! $order) {
            return;
        }

        $this->resetForm();
        $this->orderId = $order->id;
        $this->order = $order->toArray();
        $this->loadThread();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
        $this->orderId = null;
        $this->order = [];
    }

    public function submit(): void
    {
        if ($this->hasExistingComplaint) {
            return;
        }

        $this->validate([
            'category' => 'required|in:delivery,food_quality,payment,other',
            'message' => 'required|string|min:10|max:2000',
            'attachment' => 'nullable|image|max:2048',
        ], [
            'message.required' => 'Please describe your issue or request.',
            'message.min' => 'Please provide at least 10 characters.',
            'attachment.image' => 'Attachment must be an image file.',
            'attachment.max' => 'Attachment must not exceed 2MB.',
        ]);

        $userId = Auth::id();

        $complaint = OrderComplaint::create([
            'order_id' => $this->orderId,
            'parent_id' => null,
            'is_reply' => false,
            'category' => $this->category,
            'message' => $this->message,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if ($this->attachment) {
            $complaint->update([
                'attachment' => $this->storeAttachment($complaint),
                'updated_by' => $userId,
            ]);
        }

        $this->successMessage = 'Your complaint/support request has been submitted. Our team will get back to you shortly.';
        $this->category = 'delivery';
        $this->message = '';
        $this->attachment = null;
        $this->loadThread();
        $this->dispatch('corporate-orders-changed');
    }

    public function categoryLabel(?string $category): string
    {
        return match ($category) {
            'delivery' => 'Delivery Issue',
            'food_quality' => 'Food Quality',
            'payment' => 'Payment Issue',
            'other' => 'Other',
            default => 'Support',
        };
    }

    protected function loadThread(): void
    {
        $root = OrderComplaint::threadForOrder((int) $this->orderId);

        if (! $root) {
            $this->hasExistingComplaint = false;
            $this->thread = [];

            return;
        }

        $this->hasExistingComplaint = true;
        $this->thread = $root->threadMessages()
            ->map(fn (OrderComplaint $entry) => [
                'id' => $entry->id,
                'is_reply' => $entry->is_reply,
                'category' => $entry->category,
                'message' => $entry->message,
                'attachment' => $entry->attachment,
                'author_name' => $entry->createdBy?->name ?? ($entry->is_reply ? 'Middo Support' : 'You'),
                'created_at' => $entry->created_at?->toIso8601String(),
            ])
            ->all();
    }

    protected function storeAttachment(OrderComplaint $complaint): string
    {
        $relativePath = 'img/complaints';
        $directory = public_path($relativePath);

        File::ensureDirectoryExists($directory);

        $extension = strtolower($this->attachment->extension() ?: 'jpg');
        $filename = "complaint-{$complaint->id}.{$extension}";
        $destination = $directory.DIRECTORY_SEPARATOR.$filename;

        $sourcePath = $this->attachment->getRealPath();

        if (! $sourcePath || ! is_readable($sourcePath)) {
            throw new \RuntimeException('Uploaded attachment is no longer available. Please try again.');
        }

        if (file_exists($destination)) {
            File::delete($destination);
        }

        if (! File::copy($sourcePath, $destination)) {
            throw new \RuntimeException('Could not save the uploaded attachment. Please try again.');
        }

        return $relativePath.'/'.$filename;
    }

    protected function resetForm(): void
    {
        $this->resetErrorBag();
        $this->thread = [];
        $this->hasExistingComplaint = false;
        $this->category = 'delivery';
        $this->message = '';
        $this->attachment = null;
        $this->successMessage = '';
    }

    public function render()
    {
        return view('livewire.corporate.complaint-support-modal');
    }
}
