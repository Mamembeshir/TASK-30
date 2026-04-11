<div>
    <x-page-header
        title="{{ $user->profile?->fullName() ?? $user->username }}"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'route' => 'dashboard'],
            ['label' => 'User Management', 'route' => 'admin.users'],
            ['label' => $user->username],
        ]"
    />

    @if(session('success'))
        <x-toast type="success" :message="session('success')" />
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Profile card --}}
        <div class="lg:col-span-2 space-y-6">
            <x-card>
                <x-slot:header>Profile</x-slot:header>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-[#6B7280] font-medium">Full name</dt>
                        <dd class="text-[#111827] mt-0.5">{{ $user->profile?->fullName() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#6B7280] font-medium">Username</dt>
                        <dd class="text-[#111827] mt-0.5 font-mono text-xs">{{ $user->username }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#6B7280] font-medium">Email</dt>
                        <dd class="text-[#111827] mt-0.5">{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#6B7280] font-medium">Joined</dt>
                        <dd class="text-[#111827] mt-0.5">{{ $user->created_at->format('M j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#6B7280] font-medium">Status</dt>
                        <dd class="mt-0.5">
                            <x-badge :variant="$user->status->badgeVariant()">{{ $user->status->label() }}</x-badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[#6B7280] font-medium">Failed logins</dt>
                        <dd class="text-[#111827] mt-0.5">{{ $user->failed_login_count }}</dd>
                    </div>
                    @if($user->isLocked())
                        <div class="sm:col-span-2">
                            <dt class="text-[#6B7280] font-medium">Locked until</dt>
                            <dd class="text-[#DC2626] mt-0.5">{{ $user->locked_until->format('M j, Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            {{-- Role management --}}
            <x-card>
                <x-slot:header>Roles</x-slot:header>

                <form wire:submit="saveRoles" class="space-y-3">
                    @foreach($allRoles as $role)
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="selectedRoles.{{ $role->value }}"
                                class="rounded border-[#D1D5DB] text-[#1B6B93] focus:ring-[#1B6B93]"
                            />
                            <span class="text-sm text-[#111827]">{{ $role->label() }}</span>
                        </label>
                    @endforeach

                    @error('roles')
                        <p class="text-xs text-[#DC2626]">{{ $message }}</p>
                    @enderror

                    <div class="pt-2">
                        <x-button type="submit" variant="primary" size="sm">Save roles</x-button>
                    </div>
                </form>
            </x-card>
        </div>

        {{-- Actions sidebar --}}
        <div class="space-y-6">

            {{-- Status transitions --}}
            <x-card>
                <x-slot:header>Status Actions</x-slot:header>

                @error('status')
                    <p class="text-xs text-[#DC2626] mb-3">{{ $message }}</p>
                @enderror

                @if($allowedTransitions)
                    <div class="space-y-2">
                        @foreach($allowedTransitions as $target)
                            <x-button
                                wire:click="transitionTo('{{ $target->value }}')"
                                wire:confirm="Change status to {{ $target->label() }}?"
                                variant="{{ $target === \App\Enums\UserStatus::DEACTIVATED ? 'danger' : ($target === \App\Enums\UserStatus::SUSPENDED ? 'secondary' : 'primary') }}"
                                size="sm"
                                class="w-full justify-center"
                            >
                                → {{ $target->label() }}
                            </x-button>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[#6B7280]">No transitions available — this status is terminal.</p>
                @endif
            </x-card>

            {{-- Unlock --}}
            @if($user->isLocked())
                <x-card>
                    <x-slot:header>Account Lock</x-slot:header>
                    <p class="text-sm text-[#6B7280] mb-3">
                        Account is locked until {{ $user->locked_until->format('H:i, M j') }}.
                    </p>
                    <x-button
                        wire:click="unlock"
                        wire:confirm="Unlock this account?"
                        variant="secondary"
                        size="sm"
                        class="w-full justify-center"
                    >
                        Unlock account
                    </x-button>
                </x-card>
            @endif

        </div>
    </div>
</div>
