@props([
    'heading'     => 'Nothing here yet',
    'description' => null,
    'ctaLabel'    => null,
    'ctaHref'     => null,
    'ctaAction'   => null,   // wire:click value for Livewire CTA
])

<div class="flex flex-col items-center justify-center py-16 px-6 text-center">
    {{-- Icon slot --}}
    @if(isset($icon))
        <div class="mb-4 text-[#9CA3AF]">
            {{ $icon }}
        </div>
    @else
        <div class="mb-4">
            <svg class="w-16 h-16 text-[#D1D5DB] mx-auto" fill="none" viewBox="0 0 64 64" stroke="currentColor">
                <circle cx="32" cy="32" r="28" stroke-width="2"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 32h20M32 22v20"/>
            </svg>
        </div>
    @endif

    <h3 class="text-base font-semibold text-[#111827] font-display mb-1">{{ $heading }}</h3>

    @if($description)
        <p class="text-sm text-[#4B5563] max-w-sm mb-6">{{ $description }}</p>
    @endif

    @if($ctaLabel)
        @if($ctaHref)
            <x-button variant="primary" :href="$ctaHref">{{ $ctaLabel }}</x-button>
        @elseif($ctaAction)
            <x-button variant="primary" wire:click="{{ $ctaAction }}">{{ $ctaLabel }}</x-button>
        @endif
    @endif

    {{ $slot }}
</div>
