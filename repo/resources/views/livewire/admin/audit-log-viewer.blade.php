<div>
    <x-page-header title="Audit Log" :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Administration']]"/>

    <x-card>
        <x-slot:header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 w-full">
                <h2 class="text-sm font-semibold text-[#111827] font-display">Activity</h2>
                <button
                    type="button"
                    wire:click="clearFilters"
                    class="text-xs text-[#1B6B93] hover:underline"
                >Clear filters</button>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <x-input
                label="Search"
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Action, entity ID, or IP…"
            />
            <x-select
                label="Action"
                wire:model.live="actionFilter"
                :options="['' => 'All actions'] + $actionOptions->mapWithKeys(fn($a) => [$a => $a])->all()"
            />
            <x-select
                label="Entity"
                wire:model.live="entityFilter"
                :options="['' => 'All entities'] + $entityOptions->mapWithKeys(fn($e) => [$e => $e])->all()"
            />
        </div>

        <x-table :headers="[
            'when'   => 'When',
            'actor'  => 'Actor',
            'action' => 'Action',
            'entity' => 'Entity',
            'ip'     => 'IP',
        ]" empty="No audit entries match the current filters.">
            @foreach($logs as $log)
                <tr class="hover:bg-[#F8FAFC]">
                    <td class="px-4 py-3 text-sm text-[#111827] whitespace-nowrap">
                        <div>{{ $log->created_at->format('M j, Y H:i:s') }}</div>
                        <div class="text-xs text-[#9CA3AF]">{{ $log->created_at->diffForHumans() }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-[#111827]">
                        @if($log->actor)
                            <div class="font-medium">{{ $log->actor->profile?->fullName() ?? $log->actor->username }}</div>
                            <div class="text-xs text-[#9CA3AF]">{{ $log->actor->email }}</div>
                        @else
                            <span class="text-[#9CA3AF] italic">system</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm font-mono text-[#1B6B93]">{{ $log->action }}</td>
                    <td class="px-4 py-3 text-sm text-[#4B5563]">
                        <div>{{ $log->entity_type }}</div>
                        @if($log->entity_id)
                            <div class="text-xs text-[#9CA3AF] font-mono truncate max-w-[180px]" title="{{ $log->entity_id }}">
                                {{ \Illuminate\Support\Str::limit($log->entity_id, 12) }}
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-[#6B7280] font-mono">{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </x-card>
</div>
