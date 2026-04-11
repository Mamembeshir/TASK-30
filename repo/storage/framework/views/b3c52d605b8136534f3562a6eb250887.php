<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'headers' => [],  // ['column' => 'Label', ...]
    'empty'   => 'No records found.',
    'sticky'  => true,
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
    'headers' => [],  // ['column' => 'Label', ...]
    'empty'   => 'No records found.',
    'sticky'  => true,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<div class="overflow-auto rounded-xl border border-[#E5E7EB]">
    
    <table class="min-w-full divide-y divide-[#E5E7EB] hidden md:table">
        <thead class="<?php echo e($sticky ? 'sticky top-0 z-10' : ''); ?> bg-[#F1F4F8]">
            <tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $headers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <th
                        scope="col"
                        class="px-4 py-3 text-left text-xs font-semibold text-[#9CA3AF] uppercase tracking-wide whitespace-nowrap"
                    ><?php echo e($label); ?></th>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-[#E5E7EB]">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($slot->isEmpty()): ?>
                <tr>
                    <td colspan="<?php echo e(count($headers)); ?>" class="px-4 py-8 text-center text-sm text-[#9CA3AF]">
                        <?php echo e($empty); ?>

                    </td>
                </tr>
            <?php else: ?>
                <?php echo e($slot); ?>

            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>

    
    <div class="md:hidden divide-y divide-[#E5E7EB]">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($mobileCards)): ?>
            <?php echo e($mobileCards); ?>

        <?php else: ?>
            <?php echo e($slot); ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/components/table.blade.php ENDPATH**/ ?>