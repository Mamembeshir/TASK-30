<div>
    <x-page-header title="Settlements"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Settlements']]"/>

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        @if($settlements->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-gray-500">No settlements found.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Date</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Expected</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Actual</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Variance</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Payments</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($settlements as $settlement)
                        <tr>
                            <td class="px-6 py-4 text-gray-900 font-medium">{{ $settlement->settlement_date->format('M j, Y') }}</td>
                            <td class="px-6 py-4 text-gray-900">{{ formatCurrency($settlement->expected_amount_cents) }}</td>
                            <td class="px-6 py-4 text-gray-900">{{ formatCurrency($settlement->net_amount_cents) }}</td>
                            <td class="px-6 py-4 {{ abs($settlement->variance_cents) > 1 ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                {{ formatCurrency($settlement->variance_cents) }}
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $settlement->payments()->count() }}</td>
                            <td class="px-6 py-4">
                                @php $sc = match($settlement->status->value) {
                                    'OPEN'       => 'bg-yellow-100 text-yellow-700',
                                    'RECONCILED' => 'bg-green-100 text-green-700',
                                    'EXCEPTION'  => 'bg-red-100 text-red-700',
                                    default      => 'bg-gray-100 text-gray-700',
                                }; @endphp
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $sc }}">
                                    {{ $settlement->status->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('finance.settlements.show', $settlement) }}"
                                   class="text-indigo-600 hover:underline text-xs">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="px-6 py-4 border-t border-gray-200">
                {{ $settlements->links() }}
            </div>
        @endif
    </div>
</div>
