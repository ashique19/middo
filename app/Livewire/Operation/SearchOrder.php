<?php

namespace App\Livewire\Operation;

use App\Models\Order;
use Livewire\Component;

class SearchOrder extends Component
{
    public string $search = '';

    public array $orders = [];

    public function updatedSearch(): void
    {
        $this->searchOrders();
    }

    public function mount(): void
    {
        $this->searchOrders();
    }

    protected function searchOrders(): void
    {
        $term = trim($this->search);

        if ($term === '') {
            $this->orders = [];

            return;
        }

        $this->orders = Order::with(['menuItem', 'user'])
            ->where(function ($query) use ($term) {
                if (is_numeric($term)) {
                    $query->where('id', $term);
                }

                $query->orWhere('address', 'like', "%{$term}%")
                    ->orWhere('delivery_date', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($userQuery) use ($term) {
                        $userQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('mobile', 'like', "%{$term}%");
                    })
                    ->orWhereHas('menuItem', function ($menuQuery) use ($term) {
                        $menuQuery->where('name', 'like', "%{$term}%");
                    });
            })
            ->orderByDesc('delivery_date')
            ->orderByDesc('delivery_time')
            ->limit(50)
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.operation.search-order')
            ->layout('layouts.private.app', ['title' => 'Search Order']);
    }
}
