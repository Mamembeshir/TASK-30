<div>
    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Recommendations','breadcrumbs' => [['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Recommendations']]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Recommendations','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Recommendations']])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($sections)): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-12 text-center">
            <p class="text-sm text-gray-500">No recommendations available yet. Book a trip to personalise your feed!</p>
            <a href="<?php echo e(route('search')); ?>"
               class="mt-4 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Explore Trips
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-10">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <section>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4"><?php echo e($section['label']); ?></h2>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $section['trips']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $trip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a href="<?php echo e(route('trips.show', $trip)); ?>"
                               class="group flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
                                <div class="flex-1 p-4">
                                    <div class="flex items-start justify-between gap-1">
                                        <h3 class="text-sm font-semibold text-gray-900 group-hover:text-indigo-600 line-clamp-2">
                                            <?php echo e($trip->title); ?>

                                        </h3>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500"><?php echo e($trip->destination); ?></p>
                                    <p class="mt-0.5 text-xs text-gray-400"><?php echo e($trip->specialty); ?></p>
                                    <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                                        <span><?php echo e($trip->start_date->format('M j, Y')); ?></span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="rounded-full px-1.5 py-0.5 text-xs
                                            <?php echo e($trip->difficulty_level->badgeVariant() === 'success' ? 'bg-green-50 text-green-700' :
                                               ($trip->difficulty_level->badgeVariant() === 'warning' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700')); ?>">
                                            <?php echo e($trip->difficulty_level->label()); ?>

                                        </span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trip->booking_count > 0): ?>
                                            <span class="text-xs text-gray-400"><?php echo e($trip->booking_count); ?> booked</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                                <div class="border-t border-gray-100 px-4 py-2.5">
                                    <span class="text-sm font-semibold text-gray-900"><?php echo e($trip->formattedPrice()); ?></span>
                                </div>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </section>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/search/recommendations.blade.php ENDPATH**/ ?>