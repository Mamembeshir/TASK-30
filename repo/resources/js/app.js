import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Alpine is managed by Livewire's bundled version (livewire.min.js).
// Importing and starting a second Alpine instance here would create two
// competing instances and break wire:submit / wire:click directives.

// Handle expired-session (419) gracefully instead of showing Laravel's default
// "Page Expired" overlay. We replace it with a small toast and a soft reload
// so the user lands back on the same page (or login if their session is gone).
document.addEventListener('livewire:init', () => {
    if (!window.Livewire) return;

    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419) {
                preventDefault();
                showSessionExpiredToast();
                setTimeout(() => window.location.reload(), 1500);
            }
        });
    });
});

function showSessionExpiredToast() {
    if (document.getElementById('session-expired-toast')) return;

    const toast = document.createElement('div');
    toast.id = 'session-expired-toast';
    toast.setAttribute('role', 'status');
    toast.style.cssText = `
        position: fixed; top: 1rem; right: 1rem; z-index: 9999;
        background: #FEF3C7; border: 1px solid #FCD34D; color: #92400E;
        padding: 0.75rem 1rem; border-radius: 0.5rem;
        font-family: system-ui, sans-serif; font-size: 0.875rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        max-width: 320px;
    `;
    toast.textContent = 'Your session expired. Refreshing the page…';
    document.body.appendChild(toast);
}

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
