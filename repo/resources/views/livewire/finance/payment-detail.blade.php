<div>
    <x-page-header title="Payment Detail"
        :breadcrumbs="[['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Payments','route'=>'finance.payments'],['label'=>'#'.substr($payment->id,0,8)]]"/>

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main details --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Payment Details</h2>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">ID</dt>
                        <dd class="font-mono text-gray-900">{{ $payment->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Member</dt>
                        <dd class="text-gray-900">{{ $payment->user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Amount</dt>
                        <dd class="text-gray-900 font-semibold">{{ $payment->formattedAmount() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tender Type</dt>
                        <dd class="text-gray-900">{{ $payment->tender_type->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd>
                            @php $sc = match($payment->status->value) {
                                'RECORDED'  => 'bg-yellow-100 text-yellow-700',
                                'CONFIRMED' => 'bg-green-100 text-green-700',
                                'VOIDED'    => 'bg-red-100 text-red-700',
                                default     => 'bg-gray-100 text-gray-700',
                            }; @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $sc }}">
                                {{ $payment->status->label() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Reference</dt>
                        <dd class="text-gray-900 font-mono text-xs">{{ $payment->reference ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Idempotency Key</dt>
                        <dd class="text-gray-900 font-mono text-xs break-all">{{ $payment->idempotency_key }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Confirmation Event</dt>
                        <dd class="text-gray-900 font-mono text-xs">{{ $payment->confirmation_event_id ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Created</dt>
                        <dd class="text-gray-900">{{ $payment->created_at->format('M j, Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Settlement</dt>
                        <dd class="text-gray-900">
                            @if($payment->settlement)
                                <a href="{{ route('finance.settlements.show', $payment->settlement) }}"
                                   class="text-indigo-600 hover:underline">
                                    {{ $payment->settlement->settlement_date->format('M j, Y') }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Actions --}}
        <div class="space-y-4">
            @if($payment->status->value === 'RECORDED')
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Confirm Payment</h2>
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Confirmation Event ID (optional)</label>
                        <input type="text"
                               wire:model="confirmEventId"
                               placeholder="Auto-generated if blank"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"/>
                    </div>
                    @error('confirm') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    <button wire:click="confirm"
                            wire:loading.attr="disabled"
                            class="w-full rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="confirm">Confirm</span>
                        <span wire:loading wire:target="confirm">Confirming…</span>
                    </button>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Void Payment</h2>
                    @error('void') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    @if(!$showVoidConfirm)
                        <button wire:click="$set('showVoidConfirm', true)"
                                class="w-full rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Void Payment
                        </button>
                    @else
                        <p class="text-sm text-gray-600 mb-3">This will cancel the payment and any linked signups or membership orders. Confirm?</p>
                        <div class="flex gap-2">
                            <button wire:click="$set('showVoidConfirm', false)"
                                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button wire:click="void"
                                    wire:loading.attr="disabled"
                                    class="flex-1 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="void">Void</span>
                                <span wire:loading wire:target="void">Voiding…</span>
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
