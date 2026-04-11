@props([
    'expiresAt'   => null,   // ISO 8601 UTC timestamp string
    'warningAt'   => 120,    // seconds remaining when warning triggers (default 2 min)
    'onExpire'    => null,   // JS expression to run on expire (e.g. "$wire.dispatch('hold-expired')")
    'label'       => 'Time remaining',
])

<div
    x-data="{
        total: 0,
        remaining: 0,
        expired: false,
        warning: false,
        warningAt: {{ $warningAt }},
        timer: null,

        init() {
            const expiresAt = new Date('{{ $expiresAt }}').getTime();
            this.update(expiresAt);
            this.timer = setInterval(() => this.update(expiresAt), 1000);
        },

        update(expiresAt) {
            const now    = Date.now();
            const diff   = Math.max(0, Math.floor((expiresAt - now) / 1000));
            this.remaining = diff;
            this.warning   = diff <= this.warningAt && diff > 0;
            if (diff === 0 && !this.expired) {
                this.expired = true;
                clearInterval(this.timer);
                @if($onExpire) {{ $onExpire }}; @endif
            }
        },

        get minutes() { return Math.floor(this.remaining / 60); },
        get seconds() { return this.remaining % 60; },
        get display() {
            return String(this.minutes).padStart(2,'0') + ':' + String(this.seconds).padStart(2,'0');
        },
    }"
    x-init="init()"
    {{ $attributes }}
>
    <div
        x-show="!expired"
        :class="warning ? 'text-[#B91C1C]' : 'text-[#4B5563]'"
        class="flex items-center gap-2 text-sm font-medium transition-colors duration-300"
    >
        <svg class="w-4 h-4 flex-shrink-0" :class="warning ? 'animate-pulse' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>{{ $label }}:</span>
        <span
            class="font-mono text-base font-semibold tabular-nums"
            x-text="display"
        ></span>
        <span x-show="warning" class="text-xs text-[#B91C1C] font-normal ml-1">— Act now!</span>
    </div>

    <div
        x-show="expired"
        class="flex items-center gap-2 text-sm font-medium text-[#B91C1C]"
    >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>Your hold has expired.</span>
        @if(isset($expiredSlot))
            {{ $expiredSlot }}
        @endif
    </div>
</div>
