@props([
    'hover'   => false,
    'padding' => true,
])

@aware(['header' => null, 'footer' => null])

<div {{ $attributes->merge([
    'class' => 'bg-white border border-[#E5E7EB] rounded-xl shadow-sm'
        . ($hover ? ' transition-shadow duration-150 hover:shadow-md cursor-pointer' : '')
]) }}>

    @if(isset($header))
        <div class="px-6 py-4 border-b border-[#E5E7EB]">
            {{ $header }}
        </div>
    @endif

    <div @class(['px-6 py-5' => $padding])>
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div class="px-6 py-4 border-t border-[#E5E7EB] bg-[#F1F4F8] rounded-b-xl">
            {{ $footer }}
        </div>
    @endif
</div>
