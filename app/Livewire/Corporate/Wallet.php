<?php

namespace App\Livewire\Corporate;

use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Wallet extends Component
{
    public function getTransactionsProperty()
    {
        return WalletTransaction::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->limit(40)
            ->get();
    }

    public function render()
    {
        return view('livewire.corporate.wallet')
            ->layout('layouts.public.app');
    }
}
