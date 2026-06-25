<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserTable extends Component
{
    use WithPagination;

    public $search = '';

    public $roleType;

    // Listen for the event dispatched from Create/Edit modals
    protected $listeners = ['user-updated' => '$refresh'];

    public function mount($role = null)
    {
        if ($role && !\App\Models\Role::where('name', $role)->exists()) {
            abort(404); // Or redirect back with an error
        }
        $this->roleType = $role;
    }

    // Reset pagination when searching
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // Optional: Add a check to prevent deleting the logged-in user
        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot delete yourself.');
            return;
        }

        $user->delete();
        
        // Dispatch an event to notify the UI
        $this->dispatch('user-updated');
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        // Cycle through: inactive -> active -> pending -> inactive
        $statuses = ['inactive', 'active', 'pending'];
        $currentIndex = array_search($user->status, $statuses);
        $nextIndex = ($currentIndex + 1) % count($statuses);
        
        $user->update(['status' => $statuses[$nextIndex]]);
        $this->dispatch('user-updated');
    }

    public function resetUserPassword($id)
    {
        $user = User::findOrFail($id);
        $user->update(['password' => Hash::make('12345678')]);
        
        session()->flash('message', "Password reset to '12345678' for {$user->first_name}");
    }

    public function render()
    {
        $users = User::query()
            // Filter by the role passed in the URL
            ->with('role') // Add this!
            ->when($this->roleType, function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('name', $this->roleType);
                });
            })
            // Keep your existing search logic
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('first_name', 'like', '%' . $this->search . '%')
                            ->orWhere('last_name', 'like', '%' . $this->search . '%')
                            ->orWhere('mobile', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%'); // Add this if needed
                });
            })
            ->latest()
            ->paginate(10);

        

        return view('admin.users.index', compact('users'));
    }
}