<div>
    <x-page-header
        title="My Profile"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Profile']]"
    />

    @if($saved)
        <div class="mb-4 rounded-lg border border-[#A7F3D0] bg-[#ECFDF5] px-4 py-3 text-sm text-[#065F46]">
            Profile saved.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ── Account summary ───────────────────────────────────────────── --}}
        <x-card>
            <x-slot:header>
                <h2 class="text-sm font-semibold text-[#111827] font-display">Account</h2>
            </x-slot:header>

            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wider text-[#9CA3AF]">Username</dt>
                    <dd class="text-[#111827]">{{ $user->username }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wider text-[#9CA3AF]">Email</dt>
                    <dd class="text-[#111827]">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wider text-[#9CA3AF]">Status</dt>
                    <dd>
                        <x-badge :variant="$user->status->value === 'ACTIVE' ? 'success' : 'neutral'">
                            {{ $user->status->value }}
                        </x-badge>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wider text-[#9CA3AF]">Roles</dt>
                    <dd class="flex flex-wrap gap-1">
                        @forelse($roles as $role)
                            <x-badge variant="neutral">{{ $role->label() ?? $role->value }}</x-badge>
                        @empty
                            <span class="text-[#9CA3AF] italic text-xs">No roles assigned</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
        </x-card>

        {{-- ── Editable profile form ─────────────────────────────────────── --}}
        <div class="lg:col-span-2">
            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-[#111827] font-display">Personal Details</h2>
                </x-slot:header>

                <form wire:submit.prevent="save" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-input
                            label="First name"
                            type="text"
                            wire:model="firstName"
                            :error="$errors->first('firstName')"
                            required
                        />
                        <x-input
                            label="Last name"
                            type="text"
                            wire:model="lastName"
                            :error="$errors->first('lastName')"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-input
                            label="Date of birth"
                            type="date"
                            wire:model="dateOfBirth"
                            :error="$errors->first('dateOfBirth')"
                        />
                        <x-input
                            label="Phone"
                            type="tel"
                            wire:model="phone"
                            :error="$errors->first('phone')"
                        />
                    </div>

                    {{-- ── Sensitive fields ───────────────────────────── --}}
                    <div class="pt-4 mt-2 border-t border-[#F1F4F8]">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-[#6B7280] mb-3">
                            Sensitive Information
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-[#6B7280] mb-1">
                                    Current address:
                                    <span class="font-mono text-[#111827]">
                                        {{ $sensitive['address'] ?? '— not set —' }}
                                    </span>
                                </p>
                                <x-input
                                    label="New address"
                                    type="text"
                                    wire:model="address"
                                    :error="$errors->first('address')"
                                    helpText="Encrypted on save. Leave blank to keep the current value."
                                />
                            </div>

                            <div>
                                <p class="text-xs text-[#6B7280] mb-1">
                                    Current SSN fragment:
                                    <span class="font-mono text-[#111827]">
                                        {{ $sensitive['ssn_fragment'] ?? '— not set —' }}
                                    </span>
                                </p>
                                <x-input
                                    label="New SSN fragment (last 4 digits)"
                                    type="text"
                                    wire:model="ssnFragment"
                                    inputmode="numeric"
                                    maxlength="4"
                                    :error="$errors->first('ssnFragment')"
                                    helpText="Encrypted on save. Leave blank to keep the current value."
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <x-button type="submit" wire:loading.attr="disabled">
                            Save changes
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</div>
