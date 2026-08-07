<?php

namespace App\Livewire\Concerns;

trait ScrollsToAccountPanel
{
    public function openWithdrawForm(): void
    {
        $this->tab = 'withdraw';
        $this->scrollToAccountPanel('account-withdraw-panel');
    }

    protected function scrollToAccountPanel(string $elementId): void
    {
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '', $elementId) ?: 'account-panel';

        $this->js(<<<JS
            queueMicrotask(() => {
                requestAnimationFrame(() => {
                    document.getElementById('{$safeId}')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        JS);
    }
}
