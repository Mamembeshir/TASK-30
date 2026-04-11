<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'type'        => 'info',    // success | warning | danger | info
    'message'     => '',
    'autoDismiss' => 5000,      // ms; 0 = no auto-dismiss
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
    'type'        => 'info',    // success | warning | danger | info
    'message'     => '',
    'autoDismiss' => 5000,      // ms; 0 = no auto-dismiss
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $colors = [
        'success' => 'border-[#0F766E] bg-[#ECFDF5] text-[#0F766E]',
        'warning' => 'border-[#B45309] bg-[#FFFBEB] text-[#B45309]',
        'danger'  => 'border-[#B91C1C] bg-[#FEF2F2] text-[#B91C1C]',
        'info'    => 'border-[#1D4ED8] bg-[#EFF6FF] text-[#1D4ED8]',
    ];
    $colorClass = $colors[$type] ?? $colors['info'];

    $icons = [
        'success' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'warning' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>',
        'danger'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'info'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    ];
    $iconPath = $icons[$type] ?? $icons['info'];
?>

<div
    x-data="{
        show: true,
        init() {
            <?php if($autoDismiss > 0): ?>
            setTimeout(() => this.show = false, <?php echo e($autoDismiss); ?>)
            <?php endif; ?>
        }
    }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-x-8"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 translate-x-8"
    x-cloak
    <?php echo e($attributes->merge(['class' => "flex items-start gap-3 w-80 rounded-xl border-l-4 shadow-md px-4 py-3 {$colorClass}"])); ?>

    role="alert"
>
    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <?php echo $iconPath; ?>

    </svg>

    <div class="flex-1 min-w-0">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$slot->isEmpty()): ?>
            <?php echo e($slot); ?>

        <?php else: ?>
            <p class="text-sm font-medium"><?php echo e($message); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <button
        type="button"
        x-on:click="show = false"
        class="flex-shrink-0 ml-1 opacity-60 hover:opacity-100 transition-opacity focus:outline-none"
        aria-label="Dismiss"
    >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>
<?php /**PATH /var/www/html/resources/views/components/toast.blade.php ENDPATH**/ ?>