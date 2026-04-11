<div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">My Membership</h1>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="mb-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 font-semibold text-gray-900">Current Plan</h2>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($active): ?>
            <?php
                $badgeColors = match($active->plan->tier->value) {
                    'PREMIUM'  => 'bg-purple-100 text-purple-700',
                    'STANDARD' => 'bg-blue-100 text-blue-700',
                    default    => 'bg-gray-100 text-gray-700',
                };
            ?>
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <p class="text-lg font-semibold text-gray-900"><?php echo e($active->plan->name); ?></p>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($badgeColors); ?>">
                            <?php echo e($active->plan->tier->label()); ?>

                        </span>
                    </div>
                    <p class="text-sm text-gray-500">
                        Expires: <span class="font-medium text-gray-700"><?php echo e($active->expires_at->format('M j, Y')); ?></span>
                    </p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($active->isTopUpEligible()): ?>
                        <p class="mt-1 text-xs text-amber-600">
                            Upgrade window closes <?php echo e($active->top_up_eligible_until->format('M j, Y')); ?>

                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <span class="rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-700">Active</span>
            </div>
            <div class="mt-4 flex gap-3">
                <a href="<?php echo e(route('membership.index')); ?>"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    View Plans
                </a>
            </div>
        <?php else: ?>
            <p class="text-gray-500">You do not have an active membership.</p>
            <a href="<?php echo e(route('membership.index')); ?>"
               class="mt-3 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Browse Plans
            </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="font-semibold text-gray-900">Order History</h2>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($orders->isEmpty()): ?>
            <p class="px-6 py-8 text-center text-sm text-gray-500">No orders yet.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Plan</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Type</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Amount</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Expires</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="px-6 py-4 font-medium text-gray-900"><?php echo e($order->plan->name); ?></td>
                            <td class="px-6 py-4 text-gray-600"><?php echo e($order->order_type->value); ?></td>
                            <td class="px-6 py-4 text-gray-900"><?php echo e($order->formattedAmount()); ?></td>
                            <td class="px-6 py-4">
                                <?php
                                    $statusColors = match($order->status->value) {
                                        'PAID'               => 'bg-green-100 text-green-700',
                                        'PENDING'            => 'bg-yellow-100 text-yellow-700',
                                        'REFUNDED'           => 'bg-blue-100 text-blue-700',
                                        'PARTIALLY_REFUNDED' => 'bg-orange-100 text-orange-700',
                                        default              => 'bg-gray-100 text-gray-700',
                                    };
                                ?>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($statusColors); ?>">
                                    <?php echo e($order->status->label()); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600"><?php echo e($order->expires_at->format('M j, Y')); ?></td>
                            <td class="px-6 py-4">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->status->value === 'PAID'): ?>
                                    <a href="<?php echo e(route('membership.refund', $order)); ?>"
                                       class="text-sm text-red-600 hover:underline">
                                        Request Refund
                                    </a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/membership/my-membership.blade.php ENDPATH**/ ?>