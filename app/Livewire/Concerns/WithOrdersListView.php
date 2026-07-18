<?php

namespace App\Livewire\Concerns;

trait WithOrdersListView
{
    public string $viewMode = 'default';

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['default', 'list'], true) ? $mode : 'default';
    }

    public function toggleViewMode(): void
    {
        $this->viewMode = $this->viewMode === 'list' ? 'default' : 'list';
    }
}
