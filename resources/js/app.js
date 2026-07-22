// import './bootstrap';
import Alpine from 'alpinejs';

// Livewire pages inject and start Alpine themselves. Starting a second
// instance breaks $wire / wire:click on corporate Livewire screens.
window.Alpine ??= Alpine;

if (! window.Livewire) {
    Alpine.start();
}
