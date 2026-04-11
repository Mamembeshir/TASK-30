<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'variant'  => 'primary',   // primary | secondary | danger | disabled
    'type'     => 'button',
    'size'     => 'md',        // sm | md | lg
    'href'     => null,
    'disabled' => false,
    'loading'  => false,
    'icon'     => null,
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
    'variant'  => 'primary',   // primary | secondary | danger | disabled
    'type'     => 'button',
    'size'     => 'md',        // sm | md | lg
    'href'     => null,
    'disabled' => false,
    'loading'  => false,
    'icon'     => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $isDisabled = $disabled || $loading;

    $base = 'inline-flex items-center justify-center gap-2 font-semibold rounded transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 select-none';

    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-5 py-2.5 text-sm',
        'lg' => 'px-6 py-3 text-base',
    ];

    $variants = [
        'primary'   => 'bg-[#1B6B93] text-white hover:bg-[#155A7D] active:scale-[0.98] focus-visible:ring-[#1B6B93]',
        'secondary' => 'bg-white text-[#1B6B93] border border-[#1B6B93] hover:bg-[#E8F4F8] active:scale-[0.98] focus-visible:ring-[#1B6B93]',
        'danger'    => 'bg-[#B91C1C] text-white hover:bg-red-800 active:scale-[0.98] focus-visible:ring-red-600',
        'disabled'  => 'bg-[#1B6B93] text-white opacity-50 cursor-not-allowed',
    ];

    $disabledClasses = $isDisabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '';
    $variantClasses  = $variants[$isDisabled ? 'disabled' : $variant] ?? $variants['primary'];

    $classes = "{$base} {$sizes[$size]} {$variantClasses} {$disabledClasses}";
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($href && !$isDisabled): ?>
    <a href="<?php echo e($href); ?>" <?php echo e($attributes->merge(['class' => $classes])); ?>>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loading): ?>
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        <?php elseif($icon): ?>
            <span><?php echo $icon; ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php echo e($slot); ?>

    </a>
<?php else: ?>
    <button
        type="<?php echo e($type); ?>"
        <?php echo e($isDisabled ? 'disabled' : ''); ?>

        <?php echo e($attributes->merge(['class' => $classes])); ?>

    >
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loading): ?>
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        <?php elseif($icon): ?>
            <span><?php echo $icon; ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php echo e($slot); ?>

    </button>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /var/www/html/resources/views/components/button.blade.php ENDPATH**/ ?>