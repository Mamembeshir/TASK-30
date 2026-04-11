<div>
    <x-page-header title="Recommendations"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Recommendations']]"/>

    @if(empty($sections))
        <div class="rounded-xl border border-gray-200 bg-white p-12 text-center">
            <p class="text-sm text-gray-500">No recommendations available yet. Book a trip to personalise your feed!</p>
            <a href="{{ route('search') }}"
               class="mt-4 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Explore Trips
            </a>
        </div>
    @else
        <div class="space-y-10">
            @foreach($sections as $section)
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ $section['label'] }}</h2>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        @foreach($section['trips'] as $trip)
                            <a href="{{ route('trips.show', $trip) }}"
                               class="group flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
                                <div class="flex-1 p-4">
                                    <div class="flex items-start justify-between gap-1">
                                        <h3 class="text-sm font-semibold text-gray-900 group-hover:text-indigo-600 line-clamp-2">
                                            {{ $trip->title }}
                                        </h3>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">{{ $trip->destination }}</p>
                                    <p class="mt-0.5 text-xs text-gray-400">{{ $trip->specialty }}</p>
                                    <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                                        <span>{{ $trip->start_date->format('M j, Y') }}</span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="rounded-full px-1.5 py-0.5 text-xs
                                            {{ $trip->difficulty_level->badgeVariant() === 'success' ? 'bg-green-50 text-green-700' :
                                               ($trip->difficulty_level->badgeVariant() === 'warning' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">
                                            {{ $trip->difficulty_level->label() }}
                                        </span>
                                        @if($trip->booking_count > 0)
                                            <span class="text-xs text-gray-400">{{ $trip->booking_count }} booked</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="border-t border-gray-100 px-4 py-2.5">
                                    <span class="text-sm font-semibold text-gray-900">{{ $trip->formattedPrice() }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
