<div>
    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Settlements','breadcrumbs' => [['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Settlements']]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Settlements','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Settlements']])]); ?>
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

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($settlements->isEmpty()): ?>
            <p class="px-6 py-8 text-center text-sm text-gray-500">No settlements found.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Date</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Expected</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Actual</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Variance</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Payments</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $settlements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $settlement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="px-6 py-4 text-gray-900 font-medium"><?php echo e($settlement->settlement_date->format('M j, Y')); ?></td>
                            <td class="px-6 py-4 text-gray-900"><?php echo e(formatCurrency($settlement->expected_amount_cents)); ?></td>
                            <td class="px-6 py-4 text-gray-900"><?php echo e(formatCurrency($settlement->net_amount_cents)); ?></td>
                            <td class="px-6 py-4 <?php echo e(abs($settlement->variance_cents) > 1 ? 'text-red-600 font-semibold' : 'text-gray-600'); ?>">
                                <?php echo e(formatCurrency($settlement->variance_cents)); ?>

                            </td>
                            <td class="px-6 py-4 text-gray-600"><?php echo e($settlement->payments()->count()); ?></td>
                            <td class="px-6 py-4">
                                <?php $sc = match($settlement->status->value) {
                                    'OPEN'       => 'bg-yellow-100 text-yellow-700',
                                    'RECONCILED' => 'bg-green-100 text-green-700',
                                    'EXCEPTION'  => 'bg-red-100 text-red-700',
                                    default      => 'bg-gray-100 text-gray-700',
                                }; ?>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($sc); ?>">
                                    <?php echo e($settlement->status->label()); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <a href="<?php echo e(route('finance.settlements.show', $settlement)); ?>"
                                   class="text-indigo-600 hover:underline text-xs">View</a>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>

            <div class="px-6 py-4 border-t border-gray-200">
                <?php echo e($settlements->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/finance/settlement-index.blade.php ENDPATH**/ ?>