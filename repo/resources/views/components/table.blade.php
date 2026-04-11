@props([
    'headers' => [],  // ['column' => 'Label', ...]
    'empty'   => 'No records found.',
    'sticky'  => true,
])

<div class="overflow-auto rounded-xl border border-[#E5E7EB]">
    {{-- Desktop table --}}
    <table class="min-w-full divide-y divide-[#E5E7EB] hidden md:table">
        <thead class="{{ $sticky ? 'sticky top-0 z-10' : '' }} bg-[#F1F4F8]">
            <tr>
                @foreach($headers as $key => $label)
                    <th
                        scope="col"
                        class="px-4 py-3 text-left text-xs font-semibold text-[#9CA3AF] uppercase tracking-wide whitespace-nowrap"
                    >{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-[#E5E7EB]">
            @if($slot->isEmpty())
                <tr>
                    <td colspan="{{ count($headers) }}" class="px-4 py-8 text-center text-sm text-[#9CA3AF]">
                        {{ $empty }}
                    </td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>

    {{-- Mobile card view --}}
    <div class="md:hidden divide-y divide-[#E5E7EB]">
        @if(isset($mobileCards))
            {{ $mobileCards }}
        @else
            {{ $slot }}
        @endif
    </div>
</div>
