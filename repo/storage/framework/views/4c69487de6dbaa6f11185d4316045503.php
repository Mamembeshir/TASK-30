<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'type'   => 'text',     // text | card | table | avatar | list
    'lines'  => 3,
    'rows'   => 5,
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
    'type'   => 'text',     // text | card | table | avatar | list
    'lines'  => 3,
    'rows'   => 5,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $pulse = 'animate-pulse bg-[#E8ECF1] rounded';
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($type === 'text'): ?>
    <div class="space-y-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < $lines; $i++): ?>
            <div class="<?php echo e($pulse); ?> h-4 <?php echo e($i === $lines - 1 ? 'w-3/4' : 'w-full'); ?>"></div>
        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

<?php elseif($type === 'avatar'): ?>
    <div class="flex items-center gap-3">
        <div class="<?php echo e($pulse); ?> h-10 w-10 rounded-full flex-shrink-0"></div>
        <div class="flex-1 space-y-2">
            <div class="<?php echo e($pulse); ?> h-4 w-32"></div>
            <div class="<?php echo e($pulse); ?> h-3 w-24"></div>
        </div>
    </div>

<?php elseif($type === 'card'): ?>
    <div class="bg-white border border-[#E5E7EB] rounded-xl p-5 space-y-4">
        <div class="<?php echo e($pulse); ?> h-5 w-48"></div>
        <div class="space-y-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < $lines; $i++): ?>
                <div class="<?php echo e($pulse); ?> h-4 <?php echo e($i === $lines - 1 ? 'w-2/3' : 'w-full'); ?>"></div>
            <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="<?php echo e($pulse); ?> h-9 w-28 rounded-lg"></div>
    </div>

<?php elseif($type === 'table'): ?>
    <div class="border border-[#E5E7EB] rounded-xl overflow-hidden">
        
        <div class="bg-[#F1F4F8] px-4 py-3 flex gap-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < 4; $i++): ?>
                <div class="<?php echo e($pulse); ?> h-3 flex-1"></div>
            <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($r = 0; $r < $rows; $r++): ?>
            <div class="px-4 py-3.5 flex gap-4 border-t border-[#E5E7EB]">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < 4; $i++): ?>
                    <div class="<?php echo e($pulse); ?> h-4 flex-1 <?php echo e($i === 0 ? 'w-24 flex-none' : ''); ?>"></div>
                <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

<?php elseif($type === 'list'): ?>
    <div class="space-y-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < $rows; $i++): ?>
            <div class="flex items-center gap-3 p-3 bg-white border border-[#E5E7EB] rounded-lg">
                <div class="<?php echo e($pulse); ?> h-9 w-9 rounded-full flex-shrink-0"></div>
                <div class="flex-1 space-y-1.5">
                    <div class="<?php echo e($pulse); ?> h-4 w-40"></div>
                    <div class="<?php echo e($pulse); ?> h-3 w-28"></div>
                </div>
                <div class="<?php echo e($pulse); ?> h-6 w-16 rounded-full"></div>
            </div>
        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

<?php else: ?>
    
    <?php echo e($slot); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /var/www/html/resources/views/components/skeleton.blade.php ENDPATH**/ ?>