<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Component Showcase — MedVoyage</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FAFBFC] p-8 font-sans" x-data>

<div class="max-w-4xl mx-auto space-y-12">

    <div>
        <h1 class="font-display text-3xl font-semibold text-[#111827] mb-1">MedVoyage — Component Showcase</h1>
        <p class="text-[#4B5563]">Step 0 verification. All components, fonts, and CSS variables.</p>
    </div>

    {{-- Typography --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Typography</h2>
        <div class="space-y-2">
            <p class="font-display text-2xl font-bold">DM Sans Bold (display heading)</p>
            <p class="font-display text-xl font-semibold">DM Sans SemiBold (subheading)</p>
            <p class="font-display text-lg font-medium">DM Sans Medium (section title)</p>
            <p class="text-base">IBM Plex Sans Regular (body text — default sans)</p>
            <p class="text-sm font-medium">IBM Plex Sans Medium (label text)</p>
            <p class="font-semibold text-sm">IBM Plex Sans SemiBold (button text)</p>
            <p class="font-mono text-base">IBM Plex Mono Regular — $1,234.56 — ID: abc-123-def</p>
        </div>
    </section>

    {{-- Buttons --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Buttons</h2>
        <div class="flex flex-wrap gap-3">
            <x-button variant="primary">Primary Action</x-button>
            <x-button variant="secondary">Secondary Action</x-button>
            <x-button variant="danger">Danger Action</x-button>
            <x-button variant="primary" :disabled="true">Disabled</x-button>
            <x-button variant="primary" size="sm">Small</x-button>
            <x-button variant="primary" size="lg">Large</x-button>
            <x-button variant="primary" href="#" >Link Button</x-button>
        </div>
    </section>

    {{-- Badges --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Badges</h2>
        <div class="flex flex-wrap gap-2">
            <x-badge variant="success">Active</x-badge>
            <x-badge variant="warning">Pending</x-badge>
            <x-badge variant="danger">Rejected</x-badge>
            <x-badge variant="info">Submitted</x-badge>
            <x-badge variant="neutral">Draft</x-badge>
            {{-- Auto-mapped from status string --}}
            <x-badge status="approved"/>
            <x-badge status="under_review"/>
            <x-badge status="cancelled"/>
            <x-badge status="published"/>
            <x-badge status="confirmed"/>
        </div>
    </section>

    {{-- Cards --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Cards</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-card>
                <p class="font-medium text-[#111827]">Basic card</p>
                <p class="text-sm text-[#4B5563] mt-1">With default padding and border.</p>
            </x-card>
            <x-card :hover="true">
                <p class="font-medium text-[#111827]">Hover card</p>
                <p class="text-sm text-[#4B5563] mt-1">Elevates shadow on hover (150ms).</p>
            </x-card>
            <x-card>
                <x-slot:header>Card Header</x-slot:header>
                Body content here.
                <x-slot:footer>
                    <x-button variant="primary" size="sm">Action</x-button>
                </x-slot:footer>
            </x-card>
        </div>
    </section>

    {{-- Form fields --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Form Fields</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl">
            <x-input label="Full name" placeholder="Jane Smith" :required="true"/>
            <x-input label="Email" type="email" placeholder="jane@example.com" :required="true"
                     error="This email is already registered."/>
            <x-select label="Specialty" :required="true"
                      :options="['cardiology' => 'Cardiology','neurology'=>'Neurology','oncology'=>'Oncology']"
                      placeholder="Select specialty"/>
            <x-textarea label="Notes" placeholder="Additional notes…" :rows="3"/>
        </div>
    </section>

    {{-- Empty state --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Empty State</h2>
        <x-card>
            <x-empty-state
                heading="No trips found"
                description="Try adjusting your filters or check back later for new medical trips."
                cta-label="Browse All Trips"
                cta-href="#"
            />
        </x-card>
    </section>

    {{-- Skeleton loaders --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Skeleton Loaders</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-[#9CA3AF] mb-2">Text skeleton</p>
                <x-skeleton type="text" :lines="3"/>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-[#9CA3AF] mb-2">Avatar skeleton</p>
                <x-skeleton type="avatar"/>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-[#9CA3AF] mb-2">Card skeleton</p>
                <x-skeleton type="card"/>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-[#9CA3AF] mb-2">List skeleton</p>
                <x-skeleton type="list" :rows="3"/>
            </div>
        </div>
    </section>

    {{-- Table --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Table</h2>
        <x-table :headers="['name'=>'Name','role'=>'Role','status'=>'Status','joined'=>'Joined']">
            @foreach([
                ['Dr. James Wilson','Doctor','approved','Jan 5, 2025'],
                ['Alice Member','Member','active','Feb 10, 2025'],
                ['Carol Reviewer','Credentialing Reviewer','active','Mar 1, 2025'],
            ] as $row)
            <tr class="hover:bg-[#F1F4F8] transition-colors">
                <td class="px-4 py-3 text-sm font-medium text-[#111827]">{{ $row[0] }}</td>
                <td class="px-4 py-3 text-sm text-[#4B5563]">{{ $row[1] }}</td>
                <td class="px-4 py-3"><x-badge status="{{ $row[2] }}"/></td>
                <td class="px-4 py-3 text-sm text-[#4B5563] font-mono">{{ $row[3] }}</td>
            </tr>
            @endforeach
        </x-table>
    </section>

    {{-- Toast notifications --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Toast Notifications</h2>
        <div class="space-y-3 max-w-sm">
            <x-toast type="success" message="Payment confirmed successfully." :auto-dismiss="0"/>
            <x-toast type="warning" message="Your seat hold expires in 2 minutes." :auto-dismiss="0"/>
            <x-toast type="danger" message="Signup failed — no seats remaining." :auto-dismiss="0"/>
            <x-toast type="info" message="Your credentialing case has been assigned." :auto-dismiss="0"/>
        </div>
    </section>

    {{-- Countdown timer --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Countdown Timer (Alpine.js)</h2>
        <x-card>
            <p class="text-sm text-[#4B5563] mb-3">Seat hold expires in ~3 minutes. Warning turns red at 2 min.</p>
            <x-countdown
                :expires-at="now()->addMinutes(3)->toIso8601String()"
                label="Hold expires"
            />
        </x-card>
    </section>

    {{-- Modal --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Modal (Alpine.js)</h2>
        <div x-data="{ open: false }">
            <x-button variant="primary" x-on:click="open = true">Open Modal</x-button>
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div x-on:click="open = false" class="fixed inset-0 bg-black/40 backdrop-blur-[4px]"></div>
                <div class="relative z-10 w-full max-w-lg bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-[#E5E7EB]">
                        <h2 class="text-base font-semibold text-[#111827] font-display">Confirm Action</h2>
                        <button x-on:click="open = false" class="text-[#9CA3AF] hover:text-[#4B5563] p-1 rounded-lg">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="px-6 py-5">
                        <p class="text-sm text-[#4B5563]">This is a modal dialog with backdrop blur. Press Escape or click outside to close.</p>
                    </div>
                    <div class="px-6 py-4 bg-[#F1F4F8] border-t border-[#E5E7EB] flex items-center justify-end gap-3">
                        <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
                        <x-button variant="primary" x-on:click="open = false">Confirm</x-button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Color palette --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Design System Colors</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach([
                ['#1B6B93','Brand Primary'],['#155A7D','Brand Hover'],['#E8F4F8','Brand Light'],
                ['#0F766E','Success'],['#ECFDF5','Success Light'],
                ['#B45309','Warning'],['#FFFBEB','Warning Light'],
                ['#B91C1C','Danger'],['#FEF2F2','Danger Light'],
                ['#1D4ED8','Info'],['#EFF6FF','Info Light'],
                ['#111827','Text Primary'],['#4B5563','Text Secondary'],['#9CA3AF','Text Tertiary'],
                ['#FAFBFC','Surface Primary'],['#F1F4F8','Surface Secondary'],
            ] as [$hex, $label])
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded border border-[#E5E7EB] flex-shrink-0" style="background:{{ $hex }}"></div>
                <div>
                    <p class="text-xs font-semibold text-[#111827]">{{ $label }}</p>
                    <p class="text-xs font-mono text-[#9CA3AF]">{{ $hex }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </section>

    {{-- Page header --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">Page Header</h2>
        <x-card>
            <x-page-header
                title="Trip Enrollment"
                description="Manage trip signups, seat holds, and waitlists."
                :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Trips','route'=>'trips.index'],['label'=>'Enrollment']]"
            >
                <x-slot:actions>
                    <x-button variant="secondary" size="sm">Export</x-button>
                    <x-button variant="primary" size="sm">New Trip</x-button>
                </x-slot:actions>
            </x-page-header>
        </x-card>
    </section>

    {{-- formatCurrency test --}}
    <section>
        <h2 class="font-display text-xl font-semibold text-[#111827] mb-4 pb-2 border-b border-[#E5E7EB]">formatCurrency() Helper</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach([10050, 0, -500, 99, 100000, 1] as $cents)
            <x-card>
                <p class="text-xs text-[#9CA3AF]">{{ $cents }} cents</p>
                <p class="font-mono text-lg font-semibold text-[#111827]">{{ formatCurrency($cents) }}</p>
            </x-card>
            @endforeach
        </div>
        <p class="mt-2 text-sm text-[#4B5563]">
            formatCurrency(10050) = <span class="font-mono font-semibold">{{ formatCurrency(10050) }}</span>
            @if(formatCurrency(10050) === '$100.50')
                <span class="text-[#0F766E] font-semibold ml-2">✓ Correct</span>
            @else
                <span class="text-[#B91C1C] font-semibold ml-2">✗ Wrong — expected $100.50</span>
            @endif
        </p>
    </section>

</div>
</body>
</html>
