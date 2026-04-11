<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'label'    => null,
    'error'    => null,
    'required' => false,
    'helpText' => null,
    'id'       => null,
    'rows'     => 4,
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
    'label'    => null,
    'error'    => null,
    'required' => false,
    'helpText' => null,
    'id'       => null,
    'rows'     => 4,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $textareaId = $id ?? 'textarea_' . uniqid();
    $hasError   = !empty($error);
    $borderClass = $hasError
        ? 'border-[#B91C1C] focus:border-[#B91C1C] focus:ring-red-200'
        : 'border-[#E5E7EB] focus:border-[#1B6B93] focus:ring-[rgba(27,107,147,0.15)]';
?>

<div class="flex flex-col gap-1">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($label): ?>
        <label for="<?php echo e($textareaId); ?>" class="text-sm font-medium text-[#4B5563]">
            <?php echo e($label); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($required): ?>
                <span class="text-[#B91C1C] ml-0.5">*</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </label>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <textarea
        id="<?php echo e($textareaId); ?>"
        rows="<?php echo e($rows); ?>"
        <?php echo e($attributes->merge([
            'class' => "block w-full rounded-lg border px-3.5 py-2.5 text-sm text-[#111827] placeholder-[#9CA3AF]
                        bg-white focus:outline-none focus:ring-3 transition-colors duration-150 resize-y {$borderClass}"
        ])); ?>

    ><?php echo e($slot); ?></textarea>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasError): ?>
        <p class="text-xs text-[#B91C1C] mt-0.5"><?php echo e($error); ?></p>
    <?php elseif($helpText): ?>
        <p class="text-xs text-[#9CA3AF] mt-0.5"><?php echo e($helpText); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/components/textarea.blade.php ENDPATH**/ ?>