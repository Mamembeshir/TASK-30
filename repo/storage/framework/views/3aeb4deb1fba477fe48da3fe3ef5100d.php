<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'expiresAt'   => null,   // ISO 8601 UTC timestamp string
    'warningAt'   => 120,    // seconds remaining when warning triggers (default 2 min)
    'onExpire'    => null,   // JS expression to run on expire (e.g. "$wire.dispatch('hold-expired')")
    'label'       => 'Time remaining',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'expiresAt'   => null,   // ISO 8601 UTC timestamp string
    'warningAt'   => 120,    // seconds remaining when warning triggers (default 2 min)
    'onExpire'    => null,   // JS expression to run on expire (e.g. "$wire.dispatch('hold-expired')")
    'label'       => 'Time remaining',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<div
    x-data="{
        total: 0,
        remaining: 0,
        expired: false,
        warning: false,
        warningAt: <?php echo e($warningAt); ?>,
        timer: null,

        init() {
            const expiresAt = new Date('<?php echo e($expiresAt); ?>').getTime();
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
                <?php if($onExpire): ?> <?php echo e($onExpire); ?>; <?php endif; ?>
            }
        },

        get minutes() { return Math.floor(this.remaining / 60); },
        get seconds() { return this.remaining % 60; },
        get display() {
            return String(this.minutes).padStart(2,'0') + ':' + String(this.seconds).padStart(2,'0');
        },
    }"
    x-init="init()"
    <?php echo e($attributes); ?>

>
    <div
        x-show="!expired"
        :class="warning ? 'text-[#B91C1C]' : 'text-[#4B5563]'"
        class="flex items-center gap-2 text-sm font-medium transition-colors duration-300"
    >
        <svg class="w-4 h-4 flex-shrink-0" :class="warning ? 'animate-pulse' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span><?php echo e($label); ?>:</span>
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
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($expiredSlot)): ?>
            <?php echo e($expiredSlot); ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/components/countdown.blade.php ENDPATH**/ ?>