<?php

namespace App\Livewire\Shared;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class CorporateTable extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $canManage = false;

    protected $listeners = ['user-updated' => '$refresh'];

    public function mount(): void
    {
        $role = Auth::user()?->role?->name;
        abort_unless(in_array($role, ['admin', 'operation'], true), 403);

        $this->canManage = $role === 'admin';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function showRoute(User $corporate): string
    {
        return Auth::user()?->role?->name === 'admin'
            ? route('admin.corporates.show', $corporate)
            : route('operation.corporates.show', $corporate);
    }

    public function deleteUser(int $id): void
    {
        abort_unless($this->canManage, 403);

        $user = User::query()->findOrFail($id);

        if ($user->id === Auth::id()) {
            session()->flash('error', 'You cannot delete yourself.');

            return;
        }

        abort_unless($user->role?->name === 'corporate', 404);

        $user->delete();
        $this->dispatch('user-updated');
    }

    public function toggleStatus(int $id): void
    {
        abort_unless($this->canManage, 403);

        $user = User::query()->findOrFail($id);
        abort_unless($user->role?->name === 'corporate', 404);

        $statuses = ['inactive', 'active', 'pending'];
        $currentIndex = array_search($user->status, $statuses, true);
        $nextIndex = ($currentIndex === false ? 0 : $currentIndex + 1) % count($statuses);

        $user->update(['status' => $statuses[$nextIndex]]);
        $this->dispatch('user-updated');
    }

    public function resetUserPassword(int $id): void
    {
        abort_unless($this->canManage, 403);

        $user = User::query()->findOrFail($id);
        abort_unless($user->role?->name === 'corporate', 404);

        $user->update(['password' => Hash::make('12345678')]);
        session()->flash('message', "Password reset to '12345678' for {$user->first_name}");
    }

    public function render()
    {
        $term = trim($this->search);

        $corporates = User::query()
            ->with(['role', 'city', 'area'])
            ->whereHas('role', fn ($q) => $q->where('name', 'corporate'))
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($subQuery) use ($term) {
                    $like = '%'.$term.'%';
                    $subQuery->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('address', 'like', $like);
                });
            })
            ->latest()
            ->paginate(15);

        return view('livewire.shared.corporates.table', [
            'corporates' => $corporates,
        ])->layout('layouts.private.app', ['title' => 'Corporates']);
    }
}
