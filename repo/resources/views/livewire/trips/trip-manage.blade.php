<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                {{ $trip?->exists ? 'Edit Trip' : 'Create Trip' }}
            </h1>
            @if($trip?->exists)
                <p class="mt-1 text-sm text-gray-500">Status: {{ $trip->status->label() }}</p>
            @endif
        </div>

        @if($trip?->exists)
            <div class="flex gap-2">
                @if(in_array(\App\Enums\TripStatus::PUBLISHED, $trip->status->allowedTransitions()))
                    <button wire:click="publish"
                            wire:confirm="Publish this trip? It will be visible to all users."
                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                        Publish
                    </button>
                @endif
                @if(in_array(\App\Enums\TripStatus::CLOSED, $trip->status->allowedTransitions()))
                    <button wire:click="close"
                            wire:confirm="Close this trip for new signups?"
                            class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600">
                        Close
                    </button>
                @endif
                @if(in_array(\App\Enums\TripStatus::CANCELLED, $trip->status->allowedTransitions()))
                    <button wire:click="cancel"
                            wire:confirm="Cancel this trip? All active signups will be released."
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        Cancel Trip
                    </button>
                @endif
            </div>
        @endif
    </div>

    @error('status') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror
    @error('form')   <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror

    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <form wire:submit="save" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                    <input wire:model="title" type="text" maxlength="300"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea wire:model="description" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Lead Doctor <span class="text-red-500">*</span></label>
                    <select wire:model="leadDoctorId"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Select…</option>
                        @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}">{{ $doctor->user?->name }}</option>
                        @endforeach
                    </select>
                    @error('leadDoctorId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Specialty <span class="text-red-500">*</span></label>
                    <input wire:model="specialty" type="text" maxlength="200"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('specialty') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Destination <span class="text-red-500">*</span></label>
                    <input wire:model="destination" type="text" maxlength="300"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('destination') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Difficulty <span class="text-red-500">*</span></label>
                    <select wire:model="difficultyLevel"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @foreach($difficulties as $d)
                            <option value="{{ $d->value }}">{{ $d->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Start Date <span class="text-red-500">*</span></label>
                    <input wire:model="startDate" type="date"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('startDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">End Date <span class="text-red-500">*</span></label>
                    <input wire:model="endDate" type="date"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('endDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Total Seats <span class="text-red-500">*</span></label>
                    <input wire:model="totalSeats" type="number" min="1" max="500"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('totalSeats') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Price (cents)</label>
                    <input wire:model="priceCents" type="number" min="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('priceCents') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Prerequisites</label>
                    <textarea wire:model="prerequisites" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-indigo-600 px-6 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove>{{ $trip?->exists ? 'Save Changes' : 'Create Trip' }}</span>
                    <span wire:loading>Saving…</span>
                </button>
            </div>
        </form>
    </div>
</div>
