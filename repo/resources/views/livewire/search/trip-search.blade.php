<div>
    <x-page-header title="Search Trips"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Search']]"/>

    <div class="flex gap-6 items-start">

        {{-- ── Left: Filters + History sidebar ──────────────────────────────── --}}
        <aside class="w-64 flex-shrink-0 space-y-5">

            {{-- Filter panel --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Filters</h2>
                    <button wire:click="resetFilters"
                            class="text-xs text-indigo-600 hover:underline">Reset</button>
                </div>

                {{-- Specialty --}}
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Specialty</label>
                    <input type="text"
                           wire:model.live.debounce.300ms="filterSpecialty"
                           placeholder="e.g. Cardiology"
                           class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                </div>

                {{-- Date range --}}
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Start Date From</label>
                    <input type="date"
                           wire:model.live="filterDateFrom"
                           class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">End Date By</label>
                    <input type="date"
                           wire:model.live="filterDateTo"
                           class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                </div>

                {{-- Difficulty checkboxes --}}
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-2">Difficulty</label>
                    @foreach($difficulties as $d)
                        <label class="flex items-center gap-2 mb-1 text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox"
                                   wire:model.live="filterDifficulties"
                                   value="{{ $d->value }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"/>
                            {{ $d->label() }}
                        </label>
                    @endforeach
                </div>

                {{-- Duration slider --}}
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        Duration (days)
                    </label>
                    <div class="flex gap-2 items-center">
                        <input type="number" min="1" max="365"
                               wire:model.live.debounce.400ms="filterDurationMin"
                               placeholder="Min"
                               class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                        <span class="text-gray-400 text-xs">–</span>
                        <input type="number" min="1" max="365"
                               wire:model.live.debounce.400ms="filterDurationMax"
                               placeholder="Max"
                               class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                    </div>
                </div>

                {{-- Prerequisites toggle --}}
                <div>
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox"
                               wire:model.live="filterPrerequisites"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"/>
                        Has prerequisites
                    </label>
                </div>
            </div>

            {{-- Search history panel --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <button wire:click="$toggle('showHistory')"
                        class="w-full flex items-center justify-between text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                    <span>Search History</span>
                    <svg class="w-4 h-4 transition-transform {{ $showHistory ? 'rotate-180' : '' }}"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                @if($showHistory)
                    @if($history->isEmpty())
                        <p class="text-xs text-gray-400 mt-2">No recent searches.</p>
                    @else
                        <ul class="mt-2 space-y-1">
                            @foreach($history as $h)
                                <li>
                                    <button wire:click="selectSuggestion('{{ addslashes($h->query) }}')"
                                            class="text-sm text-indigo-600 hover:underline truncate block w-full text-left">
                                        {{ $h->query }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                        <button wire:click="clearHistory"
                                class="mt-3 text-xs text-red-500 hover:underline">
                            Clear History
                        </button>
                    @endif
                @endif
            </div>
        </aside>

        {{-- ── Right: Search bar + results ──────────────────────────────────── --}}
        <div class="flex-1 min-w-0">

            {{-- Search bar with Alpine.js type-ahead --}}
            <div class="mb-4" x-data="{ open: false }" @click.away="open = false">
                <div class="flex gap-3">
                    <div class="relative flex-1">
                        <input type="text"
                               wire:model.live.debounce.300ms="query"
                               @focus="open = true"
                               @input="open = true"
                               placeholder="Search by title, specialty, destination, doctor…"
                               class="w-full rounded-xl border border-gray-300 pl-4 pr-10 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </span>

                        {{-- Type-ahead dropdown --}}
                        @if(!empty($typeAheadResults))
                            <ul x-show="open"
                                class="absolute z-30 left-0 right-0 top-full mt-1 rounded-xl border border-gray-200 bg-white shadow-lg divide-y divide-gray-50 text-sm">
                                @foreach($typeAheadResults as $suggestion)
                                    <li>
                                        <button type="button"
                                                wire:click="selectSuggestion('{{ addslashes($suggestion['term']) }}')"
                                                @click="open = false"
                                                class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 text-left">
                                            <span class="text-gray-800">{{ $suggestion['term'] }}</span>
                                            @if($suggestion['category'])
                                                <span class="text-xs text-gray-400 ml-2">{{ $suggestion['category'] }}</span>
                                            @endif
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Sort dropdown --}}
                    <select wire:model.live="sort"
                            class="rounded-xl border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach($sortOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Active filter chips --}}
                @if($filterSpecialty || $filterDateFrom || $filterDateTo || !empty($filterDifficulties) || $filterDurationMin || $filterDurationMax || $filterPrerequisites)
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @if($filterSpecialty)
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                {{ $filterSpecialty }}
                                <button wire:click="$set('filterSpecialty', '')" class="hover:text-indigo-900">×</button>
                            </span>
                        @endif
                        @if($filterDateFrom)
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                From {{ $filterDateFrom }}
                                <button wire:click="$set('filterDateFrom', '')" class="hover:text-indigo-900">×</button>
                            </span>
                        @endif
                        @if($filterDateTo)
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                To {{ $filterDateTo }}
                                <button wire:click="$set('filterDateTo', '')" class="hover:text-indigo-900">×</button>
                            </span>
                        @endif
                        @foreach($filterDifficulties as $d)
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                {{ $d }}
                                <button wire:click="$set('filterDifficulties', array_diff(filterDifficulties, ['{{ $d }}']))" class="hover:text-indigo-900">×</button>
                            </span>
                        @endforeach
                        @if($filterDurationMin || $filterDurationMax)
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                {{ $filterDurationMin ?: '1' }}–{{ $filterDurationMax ?: '∞' }} days
                            </span>
                        @endif
                        @if($filterPrerequisites)
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                Has prerequisites
                                <button wire:click="$set('filterPrerequisites', false)" class="hover:text-indigo-900">×</button>
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Result count --}}
            <p class="mb-4 text-sm text-gray-500">
                {{ number_format($trips->total()) }} trip{{ $trips->total() !== 1 ? 's' : '' }} found
                @if($query) for <strong class="text-gray-700">"{{ $query }}"</strong> @endif
            </p>

            {{-- Trip cards grid --}}
            @if($trips->isEmpty())
                <div class="rounded-xl border border-gray-200 bg-white p-12 text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <p class="text-sm text-gray-500">No trips match your search. Try adjusting the filters.</p>
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($trips as $trip)
                        <a href="{{ route('trips.show', $trip) }}"
                           class="group flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
                            <div class="flex-1 p-5">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600 line-clamp-2">
                                        {{ $trip->title }}
                                    </h3>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ $trip->status->badgeVariant() === 'success' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                        {{ $trip->status->label() }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">{{ $trip->destination }}</p>
                                <p class="mt-1 text-xs text-gray-400">{{ $trip->specialty }}</p>
                                @if($trip->doctor?->user)
                                    <p class="mt-1 text-xs text-gray-400">
                                        Dr. {{ $trip->doctor->user->username }}
                                    </p>
                                @endif
                                <div class="mt-3 flex items-center gap-4 text-sm text-gray-600">
                                    <span>{{ $trip->start_date->format('M j') }}–{{ $trip->end_date->format('M j, Y') }}</span>
                                    <span class="rounded-full px-1.5 py-0.5 text-xs
                                        {{ $trip->difficulty_level->badgeVariant() === 'success' ? 'bg-green-50 text-green-700' :
                                           ($trip->difficulty_level->badgeVariant() === 'warning' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">
                                        {{ $trip->difficulty_level->label() }}
                                    </span>
                                </div>
                                @if($trip->average_rating)
                                    <p class="mt-1 text-xs text-amber-600">★ {{ number_format($trip->average_rating, 1) }}</p>
                                @endif
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3">
                                <span class="text-sm font-semibold text-gray-900">{{ $trip->formattedPrice() }}</span>
                                <span class="text-xs text-gray-500">
                                    {{ $trip->available_seats }} seat{{ $trip->available_seats === 1 ? '' : 's' }} left
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $trips->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
