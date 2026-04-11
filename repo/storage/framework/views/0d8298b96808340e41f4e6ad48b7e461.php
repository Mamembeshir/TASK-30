<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([]));

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

foreach (array_filter(([]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    use App\Enums\UserRole;
    $user = auth()->user();

    $nav = [
        'Overview' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'roles' => null],
        ],
        'Trips' => [
            ['label' => 'Browse Trips',    'route' => 'trips.index',      'icon' => 'map',    'roles' => null],
        ],
        'Membership' => [
            ['label' => 'Plans',           'route' => 'membership.index', 'icon' => 'star',   'roles' => [UserRole::MEMBER]],
            ['label' => 'My Orders',       'route' => 'membership.orders','icon' => 'receipt', 'roles' => [UserRole::MEMBER]],
        ],
        'Credentialing' => [
            ['label' => 'My Credentialing','route' => 'credentialing.profile', 'icon' => 'clipboard', 'roles' => [UserRole::DOCTOR]],
            ['label' => 'Cases',           'route' => 'credentialing.cases',   'icon' => 'clipboard', 'roles' => [UserRole::CREDENTIALING_REVIEWER, UserRole::ADMIN]],
        ],
        'Finance' => [
            ['label' => 'Dashboard',       'route' => 'finance.index',       'icon' => 'dollar',    'roles' => [UserRole::FINANCE_SPECIALIST, UserRole::ADMIN]],
            ['label' => 'Payments',        'route' => 'finance.payments',    'icon' => 'credit-card','roles' => [UserRole::FINANCE_SPECIALIST, UserRole::ADMIN]],
            ['label' => 'Settlements',     'route' => 'finance.settlements', 'icon' => 'scale',     'roles' => [UserRole::FINANCE_SPECIALIST, UserRole::ADMIN]],
            ['label' => 'Invoices',        'route' => 'finance.invoices',    'icon' => 'document',  'roles' => [UserRole::FINANCE_SPECIALIST, UserRole::ADMIN]],
        ],
        'Administration' => [
            ['label' => 'Users',           'route' => 'admin.users',  'icon' => 'users',    'roles' => [UserRole::ADMIN]],
            ['label' => 'Audit Log',       'route' => 'admin.audit',  'icon' => 'shield',   'roles' => [UserRole::ADMIN]],
            ['label' => 'Configuration',   'route' => 'admin.config', 'icon' => 'cog',      'roles' => [UserRole::ADMIN]],
        ],
    ];
?>

<div
    x-data="{ open: false }"
    x-on:toggle-sidebar.window="open = !open"
    x-on:keydown.escape.window="open = false"
>
<aside
    class="fixed inset-y-0 left-0 z-40 flex flex-col w-[260px] bg-white border-r border-[#E5E7EB] transition-transform duration-200
           lg:translate-x-0 lg:static lg:inset-auto"
    :class="open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
>
    
    <div class="flex items-center gap-3 px-5 py-5 border-b border-[#E5E7EB]">
        <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-[#1B6B93] flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 6v4m0 4h.01"/>
            </svg>
        </div>
        <span class="font-display font-semibold text-[#111827] text-base tracking-tight">MedVoyage</span>
    </div>

    
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $nav; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $visibleItems = array_filter($items, function($item) use ($user) {
                    if ($item['roles'] === null) return true;
                    foreach ($item['roles'] as $role) {
                        if ($user->hasRole($role)) return true;
                    }
                    return false;
                });
            ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($visibleItems) > 0): ?>
                <div>
                    <p class="px-3 mb-1 text-[10px] font-semibold uppercase tracking-widest text-[#9CA3AF]">
                        <?php echo e($section); ?>

                    </p>
                    <ul class="space-y-0.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $visibleItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $isActive = request()->routeIs($item['route']);
                            ?>
                            <li>
                                <a
                                    href="<?php echo e(route($item['route'])); ?>"
                                    x-on:click="open = false"
                                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-100
                                           <?php echo e($isActive
                                               ? 'bg-[#E8F4F8] text-[#1B6B93]'
                                               : 'text-[#4B5563] hover:bg-[#F1F4F8] hover:text-[#111827]'); ?>"
                                >
                                    <?php if (isset($component)) { $__componentOriginal38fc82148cc6427c9f3703d07e84369b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal38fc82148cc6427c9f3703d07e84369b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.sidebar-icon','data' => ['name' => $item['icon'],'class' => 'w-4 h-4 flex-shrink-0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('sidebar-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item['icon']),'class' => 'w-4 h-4 flex-shrink-0']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal38fc82148cc6427c9f3703d07e84369b)): ?>
<?php $attributes = $__attributesOriginal38fc82148cc6427c9f3703d07e84369b; ?>
<?php unset($__attributesOriginal38fc82148cc6427c9f3703d07e84369b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal38fc82148cc6427c9f3703d07e84369b)): ?>
<?php $component = $__componentOriginal38fc82148cc6427c9f3703d07e84369b; ?>
<?php unset($__componentOriginal38fc82148cc6427c9f3703d07e84369b); ?>
<?php endif; ?>
                                    <?php echo e($item['label']); ?>

                                </a>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </nav>

    
    <div class="border-t border-[#E5E7EB] px-4 py-3">
        <a href="<?php echo e(route('profile')); ?>" class="flex items-center gap-3 group">
            <div class="w-8 h-8 rounded-full bg-[#1B6B93] flex items-center justify-center text-white text-xs font-semibold flex-shrink-0">
                <?php echo e(strtoupper(substr($user->profile?->first_name ?? $user->username, 0, 1))); ?>

            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-[#111827] truncate group-hover:text-[#1B6B93] transition-colors">
                    <?php echo e($user->profile?->fullName() ?? $user->username); ?>

                </p>
                <p class="text-xs text-[#9CA3AF] truncate"><?php echo e($user->email); ?></p>
            </div>
        </a>
    </div>
</aside>


<div
    x-show="open"
    x-on:click="open = false"
    x-cloak
    class="fixed inset-0 z-30 bg-black/40 lg:hidden"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
></div>
</div>
<?php /**PATH /var/www/html/resources/views/components/sidebar.blade.php ENDPATH**/ ?>