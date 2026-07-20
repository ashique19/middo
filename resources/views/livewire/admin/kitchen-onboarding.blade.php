<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-middo-dark">Kitchen Onboarding</h1>
            <p class="text-sm text-gray-500 mt-1">
                Pending kitchen signups awaiting activation.
            </p>
        </div>
        <div class="relative w-full sm:w-64">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search kitchens..."
                class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl shadow-sm focus:border-middo-orange focus:ring-middo-orange">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.603 10.601z" />
            </svg>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 px-4 py-3 rounded-xl text-sm font-semibold">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[820px]">
                <thead>
                    <tr class="bg-gray-50 border-b text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">Kitchen</th>
                        <th class="p-4">Mobile</th>
                        <th class="p-4">Location</th>
                        <th class="p-4">Signed up</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($kitchens as $kitchen)
                        <tr wire:key="onboard-kitchen-{{ $kitchen->id }}" class="hover:bg-gray-50/70">
                            <td class="p-4">
                                <a href="{{ route('admin.kitchens.show', $kitchen) }}"
                                   class="font-semibold text-gray-800 hover:text-middo-orange transition">
                                    {{ $kitchen->name }}
                                </a>
                                <div class="text-[11px] font-semibold text-middo-orange mt-0.5">
                                    <a href="{{ route('admin.kitchens.show', $kitchen) }}" class="hover:underline">View details →</a>
                                </div>
                                @if($kitchen->email)
                                    <div class="text-xs text-gray-400 mt-0.5">{{ $kitchen->email }}</div>
                                @endif
                                <span class="mt-1 inline-block text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">
                                    Pending
                                </span>
                            </td>
                            <td class="p-4 text-gray-600 whitespace-nowrap">{{ $kitchen->mobile }}</td>
                            <td class="p-4 text-gray-600">
                                <div>{{ $kitchen->area?->name ?? '—' }}{{ $kitchen->city ? ', '.$kitchen->city->name : '' }}</div>
                                @if($kitchen->address)
                                    <div class="text-xs text-gray-400 max-w-xs truncate">{{ $kitchen->address }}</div>
                                @endif
                            </td>
                            <td class="p-4 text-gray-500 whitespace-nowrap">
                                {{ $kitchen->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}
                            </td>
                            <td class="p-4 text-right whitespace-nowrap">
                                <div class="inline-flex gap-2">
                                    <button type="button" wire:click="activate({{ $kitchen->id }})"
                                        wire:confirm="Activate {{ $kitchen->name }}? They will be able to log in."
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700">
                                        Activate
                                    </button>
                                    <button type="button" wire:click="suspend({{ $kitchen->id }})"
                                        wire:confirm="Suspend {{ $kitchen->name }}? They will remain inactive."
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold border border-red-200 text-red-600 hover:bg-red-50">
                                        Suspend
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-12 text-center text-gray-400 italic">
                                No pending kitchen signups.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t">{{ $kitchens->links() }}</div>
    </div>
</div>
