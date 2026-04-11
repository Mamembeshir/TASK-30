@props([
    'title'    => null,
    'maxWidth' => 'md',   // sm | md | lg | xl
    'show'     => false,
])

@aware(['footer' => null])

@php
    $widths = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];
    $maxWidthClass = $widths[$maxWidth] ?? $widths['md'];
@endphp

<div
    x-data="{ open: @js($show) }"
    x-show="open"
    x-on:open-modal.window="if ($event.detail.id === '{{ $attributes->get('id') }}') open = true"
    x-on:close-modal.window="if ($event.detail.id === '{{ $attributes->get('id') }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    {{ $attributes->except(['id', 'class']) }}
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click="open = false"
        class="fixed inset-0 bg-black/40 backdrop-blur-[4px]"
        aria-hidden="true"
    ></div>

    {{-- Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative z-10 w-full {{ $maxWidthClass }} bg-white rounded-2xl shadow-lg overflow-hidden"
    >
        {{-- Header --}}
        @if($title)
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#E5E7EB]">
            <h2 class="text-base font-semibold text-[#111827] font-display">{{ $title }}</h2>
            <button
                type="button"
                x-on:click="open = false"
                class="text-[#9CA3AF] hover:text-[#4B5563] transition-colors rounded-lg p-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1B6B93]"
                aria-label="Close"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @endif

        {{-- Body --}}
        <div class="px-6 py-5">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        @if(isset($footer))
        <div class="px-6 py-4 bg-[#F1F4F8] border-t border-[#E5E7EB] flex items-center justify-end gap-3">
            {{ $footer }}
        </div>
        @endif
    </div>
</div>
