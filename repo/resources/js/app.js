import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Alpine is managed by Livewire's bundled version (livewire.min.js).
// Importing and starting a second Alpine instance here would create two
// competing instances and break wire:submit / wire:click directives.

// Laravel Echo connected to local Reverb WebSocket server
window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    scheme: import.meta.env.VITE_REVERB_SCHEME ?? 'http',
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});
