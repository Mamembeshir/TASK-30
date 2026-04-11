@props([
    'title'       => '',
    'description' => null,
    'breadcrumbs' => [],  // [['label' => 'Home', 'route' => 'dashboard'], ['label' => 'Current']]
])

<div class="mb-8">
    {{-- Breadcrumbs --}}
    @if(count($breadcrumbs) > 0)
        <nav class="flex items-center gap-1.5 mb-3 text-sm text-[#9CA3AF]" aria-label="Breadcrumb">
            @foreach($breadcrumbs as $i => $crumb)
                @if($i > 0)
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                @endif
                @if(isset($crumb['route']) && $i < count($breadcrumbs) - 1)
                    <a href="{{ route($crumb['route']) }}" class="hover:text-[#1B6B93] transition-colors">{{ $crumb['label'] }}</a>
                @else
                    <span class="{{ $i === count($breadcrumbs) - 1 ? 'text-[#4B5563] font-medium' : '' }}">{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-[#111827] font-display">{{ $title }}</h1>
            @if($description)
                <p class="mt-1 text-sm text-[#4B5563]">{{ $description }}</p>
            @endif
        </div>

        {{-- Action buttons slot --}}
        @if(isset($actions))
            <div class="flex items-center gap-3 flex-shrink-0">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
