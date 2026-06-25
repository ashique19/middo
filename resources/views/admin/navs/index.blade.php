<x-layouts.private.app>
    <div class="max-w-5xl mx-auto py-8">
        <h1 class="text-3xl font-bold mb-8">Navigation Management</h1>

        @foreach($roles as $role)
            <div class="mb-10 border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                <div class="bg-gray-800 text-white p-4 font-bold flex justify-between items-center">
                    <span>Role: {{ $role->name }}</span>
                    <livewire:admin.nav-create-modal :roleId="$role->id" :key="'create-'.$role->id" />
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach($role->navs->whereNull('parent_id') as $parent)
                        <div class="p-4 flex justify-between items-center hover:bg-gray-50 transition">
                            <div class="flex flex-col">
                                <span class="font-semibold text-gray-800">{{ $parent->title }}</span>
                                <span class="text-xs text-gray-400 font-mono">{{ $parent->route_name }}</span>
                            </div>
                            <livewire:admin.nav-edit-modal :nav="$parent" :key="'nav-'.$parent->id" />
                        </div>

                        @foreach($parent->children as $child)
                            <div class="p-4 pl-12 bg-gray-50 flex justify-between items-center border-t border-gray-100">
                                <div class="flex flex-col">
                                    <span class="text-gray-600">↳ {{ $child->title }}</span>
                                    <span class="text-xs text-gray-400 font-mono ml-4">{{ $child->route_name }}</span>
                                </div>
                                <livewire:admin.nav-edit-modal :nav="$child" :key="'nav-'.$child->id" />
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('nav-updated', () => {
                window.location.reload();
            });
        });
    </script>
</x-layouts.private.app>