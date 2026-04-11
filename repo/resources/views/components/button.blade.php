@props([
    'variant'  => 'primary',   // primary | secondary | danger | disabled
    'type'     => 'button',
    'size'     => 'md',        // sm | md | lg
    'href'     => null,
    'disabled' => false,
    'loading'  => false,
    'icon'     => null,
])

@php
    $isDisabled = $disabled || $loading;

    $base = 'inline-flex items-center justify-center gap-2 font-semibold rounded transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 select-none';

    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-5 py-2.5 text-sm',
        'lg' => 'px-6 py-3 text-base',
    ];

    $variants = [
        'primary'   => 'bg-[#1B6B93] text-white hover:bg-[#155A7D] active:scale-[0.98] focus-visible:ring-[#1B6B93]',
        'secondary' => 'bg-white text-[#1B6B93] border border-[#1B6B93] hover:bg-[#E8F4F8] active:scale-[0.98] focus-visible:ring-[#1B6B93]',
        'danger'    => 'bg-[#B91C1C] text-white hover:bg-red-800 active:scale-[0.98] focus-visible:ring-red-600',
        'disabled'  => 'bg-[#1B6B93] text-white opacity-50 cursor-not-allowed',
    ];

    $disabledClasses = $isDisabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '';
    $variantClasses  = $variants[$isDisabled ? 'disabled' : $variant] ?? $variants['primary'];

    $classes = "{$base} {$sizes[$size]} {$variantClasses} {$disabledClasses}";
@endphp

@if($href && !$isDisabled)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($loading)
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        @elseif($icon)
            <span>{!! $icon !!}</span>
        @endif
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $isDisabled ? 'disabled' : '' }}
        {{ $attributes->merge(['class' => $classes]) }}
    >
        @if($loading)
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        @elseif($icon)
            <span>{!! $icon !!}</span>
        @endif
        {{ $slot }}
    </button>
@endif
