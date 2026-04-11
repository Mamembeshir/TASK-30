<div>
    <x-page-header
        title="My Credentialing Profile"
        :breadcrumbs="[['label' => 'Dashboard', 'route' => 'dashboard'], ['label' => 'Credentialing']]"
    />

    @if(session('success'))
        <x-toast type="success" :message="session('success')" />
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Status overview --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Credentialing status card --}}
            <x-card>
                <x-slot:header>Credentialing Status</x-slot:header>

                <div class="flex items-center gap-4 mb-4">
                    <x-badge :variant="$doctor->credentialing_status->badgeVariant()" size="lg">
                        {{ $doctor->credentialing_status->label() }}
                    </x-badge>
                    @if($doctor->activated_at)
                        <span class="text-sm text-[#6B7280]">Active since {{ $doctor->activated_at->format('M j, Y') }}</span>
                    @endif
                </div>

                @if($activeCase)
                    <div class="rounded-lg bg-[#EFF6FF] border border-[#BFDBFE] p-4 text-sm">
                        <p class="font-medium text-[#1D4ED8]">Active Case</p>
                        <p class="text-[#3B82F6] mt-1">
                            Status: <span class="font-semibold">{{ $activeCase->status->label() }}</span>
                            &nbsp;·&nbsp; Submitted {{ $activeCase->submitted_at->format('M j, Y') }}
                        </p>
                        @if($activeCase->reviewer)
                            <p class="text-[#6B7280] mt-0.5">Reviewer: {{ $activeCase->reviewer->profile?->fullName() ?? $activeCase->reviewer->username }}</p>
                        @endif
                    </div>
                @endif

                @error('submit')
                    <p class="mt-2 text-sm text-[#DC2626]">{{ $message }}</p>
                @enderror

                <div class="mt-4 flex gap-3">
                    {{-- Submit new case --}}
                    @if($doctor->credentialing_status->canSubmitNewCase() && ! $activeCase)
                        <x-button wire:click="submitCase" wire:confirm="Submit your credentialing case for review?" variant="primary" size="sm">
                            Submit for Review
                        </x-button>
                    @endif

                    {{-- Resubmit after materials requested --}}
                    @if($activeCase?->status === \App\Enums\CaseStatus::MORE_MATERIALS_REQUESTED)
                        <x-button wire:click="resubmitCase" wire:confirm="Confirm you have uploaded all requested materials?" variant="primary" size="sm">
                            Resubmit Case
                        </x-button>
                    @endif
                </div>
            </x-card>

            {{-- Documents list --}}
            <x-card>
                <x-slot:header>Documents</x-slot:header>

                @if($doctor->documents->isEmpty())
                    <x-empty-state heading="No documents yet" description="Upload your Medical License and Board Certification to begin." />
                @else
                    <div class="divide-y divide-[#E5E7EB]">
                        @foreach($doctor->documents as $doc)
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-[#111827]">{{ $doc->document_type->label() }}</p>
                                    <p class="text-xs text-[#9CA3AF]">
                                        {{ $doc->file_name }} · {{ $doc->fileSizeHuman() }} · {{ $doc->uploaded_at->format('M j, Y') }}
                                    </p>
                                </div>
                                <a
                                    href="{{ route('credentialing.documents.download', $doc) }}"
                                    class="text-sm text-[#1B6B93] hover:underline"
                                >
                                    Download
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

        </div>

        {{-- Upload sidebar --}}
        <div>
            <x-card>
                <x-slot:header>Upload Document</x-slot:header>

                <form wire:submit="uploadDocument" class="space-y-4">
                    <x-select
                        wire:model="uploadType"
                        label="Document type"
                        :error="$errors->first('uploadType')"
                        required
                    >
                        <option value="">Select type…</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </x-select>

                    <div>
                        <label class="block text-sm font-medium text-[#374151] mb-1">File <span class="text-[#DC2626]">*</span></label>
                        <input
                            type="file"
                            wire:model="uploadFile"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="block w-full text-sm text-[#6B7280] file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-[#EFF6FF] file:text-[#1B6B93] hover:file:bg-[#DBEAFE]"
                        />
                        @error('uploadFile')
                            <p class="mt-1 text-xs text-[#DC2626]">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-[#9CA3AF]">PDF, JPEG, or PNG · max 10 MB</p>
                    </div>

                    <x-button type="submit" variant="primary" size="sm" class="w-full justify-center" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="uploadDocument">Upload</span>
                        <span wire:loading wire:target="uploadDocument">Uploading…</span>
                    </x-button>
                </form>
            </x-card>
        </div>

    </div>
</div>
