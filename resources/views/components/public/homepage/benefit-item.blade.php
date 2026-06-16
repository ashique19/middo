@props(['icon', 'title', 'description'])

<div class="flex gap-4 items-start p-6 bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="shrink-0">
        <img src="{{ asset('img/home/' . $icon . '.png') }}" 
             alt="{{ $title }}" 
             class="w-10 h-10 object-contain">
    </div>
    <div>
        <h3 class="text-lg font-bold text-middo-dark mb-1">{{ $title }}</h3>
        <p class="text-gray-600 text-sm leading-relaxed">{{ $description }}</p>
    </div>
</div>