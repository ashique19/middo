<header class="bg-white shadow-sm py-4 px-8 flex justify-between items-center sticky top-0 z-40">
    <div class="text-middo-dark font-semibold">
        Welcome back, {{ Auth::user()->first_name }}
    </div>
    
    <div class="flex items-center gap-6">
        <div class="text-sm">
            Balance: <span class="font-bold text-middo-orange">৳ 0.00</span>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="text-sm text-red-600 hover:underline">Logout</button>
        </form>
    </div>
</header>