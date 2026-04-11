/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans:  ['"IBM Plex Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                display: ['"DM Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                mono:  ['"IBM Plex Mono"', 'ui-monospace', 'monospace'],
            },
            colors: {
                // Surface
                'surface-primary':   'var(--surface-primary)',
                'surface-secondary': 'var(--surface-secondary)',
                'surface-elevated':  'var(--surface-elevated)',
                'surface-sunken':    'var(--surface-sunken)',
                // Brand
                'brand':             'var(--brand-primary)',
                'brand-hover':       'var(--brand-primary-hover)',
                'brand-light':       'var(--brand-primary-light)',
                'brand-secondary':   'var(--brand-secondary)',
                // Semantic
                'success':           'var(--success)',
                'success-light':     'var(--success-light)',
                'warning':           'var(--warning)',
                'warning-light':     'var(--warning-light)',
                'danger':            'var(--danger)',
                'danger-light':      'var(--danger-light)',
                'info':              'var(--info)',
                'info-light':        'var(--info-light)',
                // Text
                'text-primary':      'var(--text-primary)',
                'text-secondary':    'var(--text-secondary)',
                'text-tertiary':     'var(--text-tertiary)',
                'text-inverse':      'var(--text-inverse)',
                // Borders
                'border-default':    'var(--border-default)',
                'border-strong':     'var(--border-strong)',
                'border-focus':      'var(--border-focus)',
            },
            borderRadius: {
                'sm':  '6px',
                DEFAULT: '8px',
                'md':  '10px',
                'lg':  '12px',
                'xl':  '16px',
                'full': '9999px',
            },
            boxShadow: {
                'sm':    'var(--shadow-sm)',
                DEFAULT: 'var(--shadow-md)',
                'md':    'var(--shadow-md)',
                'lg':    'var(--shadow-lg)',
                'focus': 'var(--shadow-focus)',
            },
            maxWidth: {
                'content': '1280px',
            },
            spacing: {
                'sidebar': '260px',
            },
            transitionDuration: {
                DEFAULT: '150ms',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
        require('@tailwindcss/typography'),
    ],
};
