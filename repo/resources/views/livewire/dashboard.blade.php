<div>
    <x-page-header title="Dashboard" />

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        @foreach($stats as $stat)
            <x-card>
                <p class="text-xs font-medium uppercase tracking-wider text-[#9CA3AF]">{{ $stat['label'] }}</p>
                <p class="mt-2 text-2xl font-display font-semibold text-[#111827]">{{ $stat['value'] }}</p>
                <p class="mt-1 text-xs text-[#6B7280]">{{ $stat['hint'] }}</p>
            </x-card>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-[#111827] font-display">Recent Activity</h2>
            </x-slot:header>

            @forelse($recentActivity as $entry)
                <div class="flex items-start gap-3 py-2 border-b border-[#F1F4F8] last:border-b-0">
                    <div class="w-8 h-8 rounded-full bg-[#E8F4F8] flex items-center justify-center text-[#1B6B93] text-xs font-semibold flex-shrink-0">
                        {{ strtoupper(substr($entry->actor?->profile?->first_name ?? $entry->actor?->username ?? '?', 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-[#111827] truncate">
                            <span class="font-medium">{{ $entry->actor?->profile?->fullName() ?? $entry->actor?->username ?? 'System' }}</span>
                            <span class="text-[#6B7280]">{{ $entry->action }}</span>
                        </p>
                        <p class="text-xs text-[#9CA3AF]">{{ $entry->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[#6B7280] py-4 text-center">No activity yet.</p>
            @endforelse
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-[#111827] font-display">Upcoming Trips</h2>
            </x-slot:header>

            @forelse($upcomingTrips as $trip)
                <a href="{{ route('trips.show', $trip) }}"
                   class="flex items-start justify-between gap-3 py-2 border-b border-[#F1F4F8] last:border-b-0 hover:bg-[#F8FAFC] -mx-2 px-2 rounded">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-[#111827] truncate">{{ $trip->title }}</p>
                        <p class="text-xs text-[#6B7280] truncate">{{ $trip->destination }} · {{ $trip->start_date->format('M j, Y') }}</p>
                    </div>
                    <span class="text-xs font-medium text-[#1B6B93] flex-shrink-0">
                        {{ $trip->available_seats }}/{{ $trip->total_seats }} seats
                    </span>
                </a>
            @empty
                <p class="text-sm text-[#6B7280] py-4 text-center">No upcoming trips.</p>
            @endforelse
        </x-card>
    </div>
</div>
