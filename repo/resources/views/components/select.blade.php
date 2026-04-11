@props([
    'label'    => null,
    'error'    => null,
    'required' => false,
    'helpText' => null,
    'id'       => null,
    'options'  => [],     // ['value' => 'label'] or [['value' => ..., 'label' => ...]]
    'placeholder' => null,
])

@php
    $selectId  = $id ?? 'select_' . uniqid();
    $hasError  = !empty($error);
    $borderClass = $hasError
        ? 'border-[#B91C1C] focus:border-[#B91C1C] focus:ring-red-200'
        : 'border-[#E5E7EB] focus:border-[#1B6B93] focus:ring-[rgba(27,107,147,0.15)]';
@endphp

<div class="flex flex-col gap-1">
    @if($label)
        <label for="{{ $selectId }}" class="text-sm font-medium text-[#4B5563]">
            {{ $label }}
            @if($required)
                <span class="text-[#B91C1C] ml-0.5">*</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $selectId }}"
        {{ $attributes->merge([
            'class' => "block w-full rounded-lg border px-3.5 py-2.5 text-sm text-[#111827]
                        bg-white focus:outline-none focus:ring-3 transition-colors duration-150
                        appearance-none cursor-pointer {$borderClass}"
        ]) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @if(!empty($options))
            @foreach($options as $value => $label)
                @if(is_array($label))
                    <option value="{{ $label['value'] }}">{{ $label['label'] }}</option>
                @else
                    <option value="{{ $value }}">{{ $label }}</option>
                @endif
            @endforeach
        @else
            {{ $slot }}
        @endif
    </select>

    @if($hasError)
        <p class="text-xs text-[#B91C1C] mt-0.5">{{ $error }}</p>
    @elseif($helpText)
        <p class="text-xs text-[#9CA3AF] mt-0.5">{{ $helpText }}</p>
    @endif
</div>
