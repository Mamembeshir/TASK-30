<div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Membership Plans</h1>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $plans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $tierColors = match($plan->tier->value) {
                    'PREMIUM'  => 'border-purple-400 bg-purple-50',
                    'STANDARD' => 'border-blue-400 bg-blue-50',
                    default    => 'border-gray-300 bg-white',
                };
                $badgeColors = match($plan->tier->value) {
                    'PREMIUM'  => 'bg-purple-100 text-purple-700',
                    'STANDARD' => 'bg-blue-100 text-blue-700',
                    default    => 'bg-gray-100 text-gray-700',
                };
                $isCurrent   = $active && $active->plan_id === $plan->id;
                $canUpgrade  = $active && $plan->tier->isHigherThan($active->plan->tier) && $active->isTopUpEligible();
                $canPurchase = $active === null;
            ?>
            <div class="rounded-xl border-2 p-6 flex flex-col <?php echo e($tierColors); ?> <?php echo e($isCurrent ? 'ring-2 ring-indigo-500' : ''); ?>">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-lg font-semibold text-gray-900"><?php echo e($plan->name); ?></h2>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($badgeColors); ?>">
                        <?php echo e($plan->tier->label()); ?>

                    </span>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($plan->description): ?>
                    <p class="text-sm text-gray-600 flex-1 mb-4"><?php echo e($plan->description); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="mt-auto">
                    <p class="text-3xl font-bold text-gray-900"><?php echo e($plan->formattedPrice()); ?></p>
                    <p class="text-sm text-gray-500">for <?php echo e($plan->duration_months); ?> month(s)</p>

                    <div class="mt-4">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCurrent): ?>
                            <span class="block w-full rounded-lg bg-indigo-100 px-4 py-2 text-center text-sm font-medium text-indigo-700">
                                Current Plan
                            </span>
                        <?php elseif($canUpgrade): ?>
                            <a href="<?php echo e(route('membership.top-up', $plan)); ?>"
                               class="block w-full rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-blue-700">
                                Upgrade to <?php echo e($plan->tier->label()); ?>

                            </a>
                        <?php elseif($canPurchase): ?>
                            <a href="<?php echo e(route('membership.purchase', $plan)); ?>"
                               class="block w-full rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-indigo-700">
                                Purchase
                            </a>
                        <?php else: ?>
                            <span class="block w-full rounded-lg bg-gray-100 px-4 py-2 text-center text-sm text-gray-400">
                                Not Available
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($plans->isEmpty()): ?>
            <p class="col-span-full text-center text-gray-500 py-12">No membership plans available.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/membership/plan-catalog.blade.php ENDPATH**/ ?>