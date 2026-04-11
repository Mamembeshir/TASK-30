@props([
    'type'   => 'text',     // text | card | table | avatar | list
    'lines'  => 3,
    'rows'   => 5,
])

@php
    $pulse = 'animate-pulse bg-[#E8ECF1] rounded';
@endphp

@if($type === 'text')
    <div class="space-y-2">
        @for($i = 0; $i < $lines; $i++)
            <div class="{{ $pulse }} h-4 {{ $i === $lines - 1 ? 'w-3/4' : 'w-full' }}"></div>
        @endfor
    </div>

@elseif($type === 'avatar')
    <div class="flex items-center gap-3">
        <div class="{{ $pulse }} h-10 w-10 rounded-full flex-shrink-0"></div>
        <div class="flex-1 space-y-2">
            <div class="{{ $pulse }} h-4 w-32"></div>
            <div class="{{ $pulse }} h-3 w-24"></div>
        </div>
    </div>

@elseif($type === 'card')
    <div class="bg-white border border-[#E5E7EB] rounded-xl p-5 space-y-4">
        <div class="{{ $pulse }} h-5 w-48"></div>
        <div class="space-y-2">
            @for($i = 0; $i < $lines; $i++)
                <div class="{{ $pulse }} h-4 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
            @endfor
        </div>
        <div class="{{ $pulse }} h-9 w-28 rounded-lg"></div>
    </div>

@elseif($type === 'table')
    <div class="border border-[#E5E7EB] rounded-xl overflow-hidden">
        {{-- Header --}}
        <div class="bg-[#F1F4F8] px-4 py-3 flex gap-4">
            @for($i = 0; $i < 4; $i++)
                <div class="{{ $pulse }} h-3 flex-1"></div>
            @endfor
        </div>
        {{-- Rows --}}
        @for($r = 0; $r < $rows; $r++)
            <div class="px-4 py-3.5 flex gap-4 border-t border-[#E5E7EB]">
                @for($i = 0; $i < 4; $i++)
                    <div class="{{ $pulse }} h-4 flex-1 {{ $i === 0 ? 'w-24 flex-none' : '' }}"></div>
                @endfor
            </div>
        @endfor
    </div>

@elseif($type === 'list')
    <div class="space-y-3">
        @for($i = 0; $i < $rows; $i++)
            <div class="flex items-center gap-3 p-3 bg-white border border-[#E5E7EB] rounded-lg">
                <div class="{{ $pulse }} h-9 w-9 rounded-full flex-shrink-0"></div>
                <div class="flex-1 space-y-1.5">
                    <div class="{{ $pulse }} h-4 w-40"></div>
                    <div class="{{ $pulse }} h-3 w-28"></div>
                </div>
                <div class="{{ $pulse }} h-6 w-16 rounded-full"></div>
            </div>
        @endfor
    </div>

@else
    {{-- Custom skeleton via slot --}}
    {{ $slot }}
@endif
