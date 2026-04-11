<div>
    <x-page-header
        title="User Management"
        :breadcrumbs="[['label' => 'Dashboard', 'route' => 'dashboard'], ['label' => 'Administration']]"
    />

    {{-- Filters --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <x-input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name, username or email…"
                type="search"
            />
        </div>

        <x-select wire:model.live="filterStatus" label="">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </x-select>

        <x-select wire:model.live="filterRole" label="">
            <option value="">All roles</option>
            @foreach($roles as $role)
                <option value="{{ $role->value }}">{{ $role->label() }}</option>
            @endforeach
        </x-select>
    </div>

    {{-- Table --}}
    @if($users->isEmpty())
        <x-empty-state heading="No users found" description="Try adjusting your filters." />
    @else
        <x-card>
            <x-table>
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[#6B7280] uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[#6B7280] uppercase tracking-wider">Roles</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[#6B7280] uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[#6B7280] uppercase tracking-wider">Joined</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-[#6B7280] uppercase tracking-wider">Actions</th>
                    </tr>
                </x-slot:head>
                <x-slot:body>
                    @foreach($users as $user)
                        <tr class="hover:bg-[#F9FAFB] border-b border-[#E5E7EB] last:border-0">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-[#1B6B93] flex items-center justify-center text-white text-xs font-semibold shrink-0">
                                        {{ strtoupper(substr($user->profile?->first_name ?? $user->username, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-[#111827]">
                                            {{ $user->profile?->fullName() ?? $user->username }}
                                        </p>
                                        <p class="text-xs text-[#6B7280]">{{ $user->email }}</p>
                                        <p class="text-xs text-[#9CA3AF]">@{{ $user->username }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($user->roles as $role)
                                        <x-badge variant="neutral" size="sm">
                                            {{ \App\Enums\UserRole::from($role->role)->label() }}
                                        </x-badge>
                                    @empty
                                        <span class="text-xs text-[#9CA3AF]">No roles</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$user->status->badgeVariant()">
                                    {{ $user->status->label() }}
                                </x-badge>
                                @if($user->isLocked())
                                    <x-badge variant="warning" size="sm" class="ml-1">Locked</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-[#6B7280]">
                                {{ $user->created_at->format('M j, Y') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.users.show', $user) }}"
                                    wire:navigate
                                    class="text-sm text-[#1B6B93] hover:underline font-medium"
                                >
                                    Manage
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>

            <div class="px-4 py-3 border-t border-[#E5E7EB]">
                {{ $users->links() }}
            </div>
        </x-card>
    @endif
</div>
