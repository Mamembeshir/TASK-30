<div>
    <x-page-header
        title="Case Review"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'route' => 'dashboard'],
            ['label' => 'Cases', 'route' => 'credentialing.cases'],
            ['label' => 'Case #' . substr($case->id, 0, 8)],
        ]"
    />

    @if(session('success'))
        <x-toast type="success" :message="session('success')" />
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: doctor info + documents + timeline --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Doctor info --}}
            <x-card>
                <x-slot:header>Doctor</x-slot:header>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-[#6B7280] font-medium">Name</dt>
                        <dd class="text-[#111827]">{{ $case->doctor->user?->profile?->fullName() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#6B7280] font-medium">Specialty</dt>
                        <dd class="text-[#111827]">{{ $case->doctor->specialty }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#6B7280] font-medium">License State</dt>
                        <dd class="text-[#111827]">{{ $case->doctor->license_state ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#6B7280] font-medium">License Expiry</dt>
                        <dd class="{{ $case->doctor->isLicenseExpired() ? 'text-[#DC2626] font-semibold' : 'text-[#111827]' }}">
                            {{ $case->doctor->license_expiry?->format('M j, Y') ?? '—' }}
                            @if($case->doctor->isLicenseExpired()) (EXPIRED) @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[#6B7280] font-medium">Case Status</dt>
                        <dd><x-badge :variant="$case->status->badgeVariant()">{{ $case->status->label() }}</x-badge></dd>
                    </div>
                    <div>
                        <dt class="text-[#6B7280] font-medium">Submitted</dt>
                        <dd class="text-[#111827]">{{ $case->submitted_at->format('M j, Y H:i') }}</dd>
                    </div>
                </dl>
            </x-card>

            {{-- Documents --}}
            <x-card>
                <x-slot:header>Documents</x-slot:header>
                @forelse($case->doctor->documents as $doc)
                    <div class="flex items-center justify-between py-2.5 border-b border-[#E5E7EB] last:border-0 text-sm">
                        <div>
                            <span class="font-medium text-[#111827]">{{ $doc->document_type->label() }}</span>
                            <span class="text-[#9CA3AF] ml-2">{{ $doc->fileSizeHuman() }} · {{ $doc->uploaded_at->format('M j, Y') }}</span>
                            @if($doc->uploaded_by !== $case->doctor->user_id)
                                <span class="ml-2 text-xs text-[#6B7280] italic">uploaded by staff</span>
                            @endif
                        </div>
                        <a href="{{ route('credentialing.documents.download', $doc) }}" class="text-[#1B6B93] hover:underline">Download</a>
                    </div>
                @empty
                    <p class="text-sm text-[#9CA3AF]">No documents uploaded.</p>
                @endforelse

                @if($canUpload)
                    <div class="mt-4 pt-4 border-t border-[#E5E7EB]">
                        <p class="text-xs font-semibold text-[#374151] uppercase tracking-wide mb-3">Upload on behalf of doctor</p>
                        @error('staffUploadFile')
                            <p class="mb-2 text-xs text-[#DC2626]">{{ $message }}</p>
                        @enderror
                        <form wire:submit="uploadDocument" class="space-y-3">
                            <x-select
                                wire:model="staffUploadType"
                                label="Document type"
                                :error="$errors->first('staffUploadType')"
                                required
                            >
                                <option value="">Select type…</option>
                                @foreach($documentTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </x-select>
                            <div>
                                <label class="block text-sm font-medium text-[#374151] mb-1">
                                    File <span class="text-[#DC2626]">*</span>
                                </label>
                                <input
                                    type="file"
                                    wire:model="staffUploadFile"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="block w-full text-sm text-[#6B7280] file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-[#EFF6FF] file:text-[#1B6B93] hover:file:bg-[#DBEAFE]"
                                />
                                <p class="mt-1 text-xs text-[#9CA3AF]">PDF, JPEG, or PNG · max 10 MB</p>
                            </div>
                            <x-button type="submit" variant="secondary" size="sm" class="w-full justify-center" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="uploadDocument">Upload Document</span>
                                <span wire:loading wire:target="uploadDocument">Uploading…</span>
                            </x-button>
                        </form>
                    </div>
                @endif
            </x-card>

            {{-- Action timeline --}}
            <x-card>
                <x-slot:header>History</x-slot:header>
                <ol class="relative ml-3">
                    @foreach($case->actions as $action)
                        <li class="mb-6 ml-6">
                            <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full bg-[#EFF6FF] ring-4 ring-white text-[#1B6B93] text-xs font-bold">
                                {{ $loop->iteration }}
                            </span>
                            <p class="text-sm font-semibold text-[#111827]">{{ $action->action->label() }}</p>
                            <p class="text-xs text-[#6B7280]">
                                {{ $action->actor?->profile?->fullName() ?? $action->actor?->username ?? 'System' }}
                                &nbsp;·&nbsp; {{ $action->timestamp->format('M j, Y H:i') }}
                            </p>
                            @if($action->notes)
                                <blockquote class="mt-1 text-sm text-[#4B5563] border-l-2 border-[#D1D5DB] pl-3 italic">{{ $action->notes }}</blockquote>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-card>
        </div>

        {{-- Right: action panel --}}
        <div class="space-y-4">

            @error('action')
                <div class="rounded-lg bg-[#FEF2F2] border border-[#FECACA] px-4 py-3 text-sm text-[#DC2626]">{{ $message }}</div>
            @enderror

            {{-- Assign reviewer --}}
            @if($case->status === \App\Enums\CaseStatus::SUBMITTED)
                <x-card>
                    <x-slot:header>Assign Reviewer</x-slot:header>
                    <div class="space-y-3">
                        <x-input wire:model.live.debounce.300ms="reviewerSearch" placeholder="Search reviewer name…" />
                        @if($reviewers->isNotEmpty())
                            <div class="rounded border border-[#E5E7EB] divide-y divide-[#E5E7EB] max-h-40 overflow-y-auto">
                                @foreach($reviewers as $reviewer)
                                    <button
                                        type="button"
                                        wire:click="$set('selectedReviewerId', '{{ $reviewer->id }}')"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-[#F3F4F6] {{ $selectedReviewerId === $reviewer->id ? 'bg-[#EFF6FF] text-[#1B6B93]' : '' }}"
                                    >
                                        {{ $reviewer->profile?->fullName() ?? $reviewer->username }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @error('selectedReviewerId') <p class="text-xs text-[#DC2626]">{{ $message }}</p> @enderror
                        <x-button wire:click="assignReviewer" variant="primary" size="sm" class="w-full justify-center">
                            Assign Reviewer
                        </x-button>
                    </div>
                </x-card>
            @endif

            {{-- Start review --}}
            @if($case->status === \App\Enums\CaseStatus::SUBMITTED && $case->assigned_reviewer)
                <x-button wire:click="startReview" variant="primary" size="sm" class="w-full justify-center">
                    Start Review
                </x-button>
            @endif

            {{-- Request materials --}}
            @if(in_array(\App\Enums\CaseStatus::MORE_MATERIALS_REQUESTED, $allowedTransitions))
                <x-card>
                    <x-slot:header>Request Materials</x-slot:header>
                    <div class="space-y-3">
                        <x-textarea wire:model="notes" label="Notes for doctor" :error="$errors->first('notes')" rows="3" required />
                        <x-button wire:click="requestMaterials" variant="secondary" size="sm" class="w-full justify-center">
                            Request Materials
                        </x-button>
                    </div>
                </x-card>
            @endif

            {{-- Approve / Reject --}}
            @if(in_array(\App\Enums\CaseStatus::APPROVED, $allowedTransitions))
                <x-button
                    wire:click="approve"
                    wire:confirm="Approve this doctor? They will be able to lead trips."
                    variant="primary"
                    size="sm"
                    class="w-full justify-center"
                >
                    Approve Doctor
                </x-button>
            @endif

            @if(in_array(\App\Enums\CaseStatus::REJECTED, $allowedTransitions))
                <x-card>
                    <x-slot:header>Reject Case</x-slot:header>
                    <div class="space-y-3">
                        <x-textarea wire:model="notes" label="Rejection reason" :error="$errors->first('notes')" rows="3" required />
                        <x-button
                            wire:click="reject"
                            wire:confirm="Reject this case? This is final for this case."
                            variant="danger"
                            size="sm"
                            class="w-full justify-center"
                        >
                            Reject Case
                        </x-button>
                    </div>
                </x-card>
            @endif

            @if($case->status->isTerminal())
                <div class="rounded-lg bg-[#F9FAFB] border border-[#E5E7EB] px-4 py-3 text-sm text-[#6B7280] text-center">
                    This case is {{ $case->status->label() }} — no further actions available.
                    @if($case->resolved_at)
                        <br>Resolved {{ $case->resolved_at->format('M j, Y') }}.
                    @endif
                </div>
            @endif

        </div>
    </div>
</div>
