<div> 
    <div class="block w-full max-w-6xl mx-auto py-8 px-4 sm:px-6 overflow-hidden">
        
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <h1 class="text-3xl font-bold">User Management</h1>
            
            <div class="flex items-center gap-4 w-full sm:w-auto">
                <div class="relative w-full sm:w-64">
                    <input wire:model.live.debounce.300ms="search" 
                        type="text" 
                        placeholder="Search users..." 
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition">
                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <livewire:admin.user-create-modal />
            </div>
        </div>

        <div class="w-full bg-white shadow-sm border border-gray-100 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto w-full">
                @if (session()->has('message'))
                    <div class="bg-blue-500 text-white p-2 rounded mb-4">
                        {{ session('message') }}
                    </div>
                @endif
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="p-4 font-semibold text-gray-600 whitespace-nowrap">Name</th>
                            <th class="p-4 font-semibold text-gray-600 whitespace-nowrap">Email</th>
                            <th class="p-4 font-semibold text-gray-600 whitespace-nowrap">Role</th>
                            <th class="p-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($users as $user)
                            <tr class="hover:bg-gray-50" wire:key="user-{{ $user->id }}">
                                <td class="p-4 font-medium whitespace-nowrap">{{ $user->first_name }} {{ $user->last_name }}</td>
                                <td class="p-4 text-gray-500 whitespace-nowrap">{{ $user->email ?: 'N/A' }}</td>
                                <td class="p-4 whitespace-nowrap">
                                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-lg text-xs font-bold uppercase truncate block max-w-[150px]">
                                        {{ $user->role->name ?? 'No Role' }}
                                    </span>
                                </td>
                                <td class="p-4 text-right whitespace-nowrap flex justify-end gap-3">
                                    <button wire:click="toggleStatus({{ $user->id }})"
                                            class="px-2 py-1 rounded text-xs font-bold uppercase 
                                            {{ $user->status == 'active' ? 'bg-green-100 text-green-700' : 
                                            ($user->status == 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">
                                        {{ $user->status }}
                                    </button>

                                    <button wire:click="resetUserPassword({{ $user->id }})"
                                            wire:confirm="Reset password for {{ $user->first_name }} to '12345678'?"
                                            class="text-blue-600 hover:text-blue-900 text-sm">
                                        Reset Pass
                                    </button>

                                    <livewire:admin.user-edit-modal :user="$user" :key="'edit-'.$user->id" />
                                    
                                    <button wire:click="deleteUser({{ $user->id }})"
                                            wire:confirm="Are you sure?"
                                            class="text-red-600 hover:text-red-900 text-sm">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-gray-500">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>
