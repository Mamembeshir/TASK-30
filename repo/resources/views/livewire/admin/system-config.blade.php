<div>
    <x-page-header title="System Configuration" :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Administration']]"/>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-[#111827] font-display">Application settings</h2>
            </x-slot:header>
            <p class="text-xs text-[#6B7280] mb-4">
                Read-only view of runtime configuration. To change these values, edit the
                <code class="px-1 py-0.5 bg-[#F1F4F8] rounded text-[#1B6B93]">.env</code> file
                and restart the app container.
            </p>
            <dl class="divide-y divide-[#F1F4F8]">
                @foreach($config as $row)
                    <div class="py-3">
                        <dt class="text-sm font-medium text-[#111827]">{{ $row['label'] }}</dt>
                        <dd class="mt-0.5 text-sm text-[#1B6B93] font-mono">{{ $row['value'] }}</dd>
                        <dd class="mt-0.5 text-xs text-[#9CA3AF]">{{ $row['description'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-[#111827] font-display">Environment</h2>
            </x-slot:header>
            <dl class="divide-y divide-[#F1F4F8]">
                @foreach($environment as $key => $value)
                    <div class="py-2.5 flex items-center justify-between gap-4">
                        <dt class="text-sm text-[#6B7280]">{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                        <dd class="text-sm font-mono text-[#111827] truncate">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        <x-card class="lg:col-span-2">
            <x-slot:header>
                <h2 class="text-sm font-semibold text-[#111827] font-display">Database statistics</h2>
            </x-slot:header>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach($stats as $key => $count)
                    <div class="text-center p-3 rounded-lg bg-[#F8FAFC]">
                        <p class="text-2xl font-display font-semibold text-[#111827]">{{ number_format($count) }}</p>
                        <p class="text-xs text-[#6B7280] mt-1 capitalize">{{ str_replace('_', ' ', $key) }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>
</div>
