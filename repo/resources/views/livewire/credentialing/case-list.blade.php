<div>
    <x-page-header
        title="Credentialing Cases"
        :breadcrumbs="[['label' => 'Dashboard', 'route' => 'dashboard'], ['label' => 'Credentialing']]"
    />

    {{-- Filters --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <x-input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by doctor name or email…"
                type="search"
            />
        </div>
        <x-select wire:model.live="filterStatus" label="">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </x-select>
    </div>

    @if($cases->isEmpty())
        <x-empty-state heading="No cases found" description="Adjust your filters or wait for new submissions." />
    @else
        <x-card>
            <x-table>
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[#6B7280] uppercase tracking-wider">Doctor</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[#6B7280] uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[#6B7280] uppercase tracking-wider">Reviewer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[#6B7280] uppercase tracking-wider">Submitted</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-[#6B7280] uppercase tracking-wider">Action</th>
                    </tr>
                </x-slot:head>
                <x-slot:body>
                    @foreach($cases as $case)
                        <tr class="hover:bg-[#F9FAFB] border-b border-[#E5E7EB] last:border-0">
                            <td class="px-4 py-3">
                                <div>
                                    <p class="text-sm font-medium text-[#111827]">
                                        {{ $case->doctor->user?->profile?->fullName() ?? $case->doctor->user?->username ?? 'Unknown' }}
                                    </p>
                                    <p class="text-xs text-[#9CA3AF]">{{ $case->doctor->specialty }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$case->status->badgeVariant()">{{ $case->status->label() }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-[#6B7280]">
                                {{ $case->reviewer?->profile?->fullName() ?? $case->reviewer?->username ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-[#6B7280]">
                                {{ $case->submitted_at->format('M j, Y') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('credentialing.cases.show', $case) }}"
                                    wire:navigate
                                    class="text-sm text-[#1B6B93] hover:underline font-medium"
                                >
                                    Review
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>
            <div class="px-4 py-3 border-t border-[#E5E7EB]">
                {{ $cases->links() }}
            </div>
        </x-card>
    @endif
</div>
