<div>
    <x-page-header title="Dashboard" />

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        {{-- Stats cards will be populated in later steps --}}
        @foreach(['Active Trips', 'Pending Credentialing', 'Today\'s Payments', 'Members'] as $stat)
        <x-card>
            <x-skeleton type="text" :lines="2"/>
        </x-card>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-[#111827] font-display">Recent Activity</h2>
            </x-slot:header>
            <x-skeleton type="list" :rows="4"/>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-[#111827] font-display">Upcoming Trips</h2>
            </x-slot:header>
            <x-skeleton type="list" :rows="4"/>
        </x-card>
    </div>
</div>
