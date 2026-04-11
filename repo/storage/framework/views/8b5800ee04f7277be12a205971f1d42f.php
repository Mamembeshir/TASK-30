<div>
    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Finance Dashboard','breadcrumbs' => [['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance']]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Finance Dashboard','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance']])]); ?>
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

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <p><?php echo e($error); ?></p>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex gap-4 text-sm font-medium">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['payments' => 'Payments', 'refunds' => 'Refunds', 'settlement' => 'Settlement', 'exceptions' => 'Exceptions', 'invoices' => 'Invoices']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <button wire:click="$set('tab', '<?php echo e($t); ?>')"
                        class="<?php echo e($tab === $t ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700'); ?> pb-3 px-1">
                    <?php echo e($label); ?>

                </button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </nav>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'payments'): ?>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Recent Payments</h2>
            <a href="<?php echo e(route('finance.payments')); ?>" class="text-sm text-indigo-600 hover:underline">View all</a>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payments->isEmpty()): ?>
                <p class="px-6 py-8 text-center text-sm text-gray-500">No payments found.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">ID</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Member</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Amount</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Tender</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Date</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="px-6 py-4 font-mono text-xs text-gray-500"><?php echo e(substr($payment->id, 0, 8)); ?></td>
                                <td class="px-6 py-4 text-gray-900"><?php echo e($payment->user->email); ?></td>
                                <td class="px-6 py-4 text-gray-900"><?php echo e($payment->formattedAmount()); ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo e($payment->tender_type->label()); ?></td>
                                <td class="px-6 py-4">
                                    <?php $sc = match($payment->status->value) {
                                        'RECORDED'  => 'bg-yellow-100 text-yellow-700',
                                        'CONFIRMED' => 'bg-green-100 text-green-700',
                                        'VOIDED'    => 'bg-red-100 text-red-700',
                                        default     => 'bg-gray-100 text-gray-700',
                                    }; ?>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($sc); ?>">
                                        <?php echo e($payment->status->label()); ?>

                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500"><?php echo e($payment->created_at->format('M j, Y')); ?></td>
                                <td class="px-6 py-4">
                                    <a href="<?php echo e(route('finance.payments.show', $payment)); ?>"
                                       class="text-indigo-600 hover:underline text-xs">View</a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'refunds'): ?>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Recent Refunds</h2>
            <a href="<?php echo e(route('finance.refunds')); ?>" class="text-sm text-indigo-600 hover:underline">Manage refunds</a>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refunds->isEmpty()): ?>
                <p class="px-6 py-8 text-center text-sm text-gray-500">No refunds found.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Member</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Amount</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $refunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $refund): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="px-6 py-4 text-gray-900"><?php echo e($refund->payment->user->email); ?></td>
                                <td class="px-6 py-4 text-gray-900"><?php echo e($refund->formattedAmount()); ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo e($refund->refund_type->label()); ?></td>
                                <td class="px-6 py-4">
                                    <?php $sc = match($refund->status->value) {
                                        'RECORDED'  => 'bg-yellow-100 text-yellow-700',
                                        'APPROVED'  => 'bg-blue-100 text-blue-700',
                                        'PROCESSED' => 'bg-green-100 text-green-700',
                                        'REJECTED'  => 'bg-red-100 text-red-700',
                                        default     => 'bg-gray-100 text-gray-700',
                                    }; ?>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($sc); ?>">
                                        <?php echo e($refund->status->label()); ?>

                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500"><?php echo e($refund->created_at->format('M j, Y')); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'settlement'): ?>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Daily Settlement</h2>
            <a href="<?php echo e(route('finance.settlements')); ?>" class="text-sm text-indigo-600 hover:underline">View all</a>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Close Settlement for Date</h3>
            <div class="flex gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Date</label>
                    <input type="date" wire:model="settlementDate"
                           class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                </div>
                <button wire:click="closeSettlement"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="closeSettlement">Close Settlement</span>
                    <span wire:loading wire:target="closeSettlement">Processing…</span>
                </button>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['settlement'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-2 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($settlements->isEmpty()): ?>
                <p class="px-6 py-8 text-center text-sm text-gray-500">No settlements yet.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Date</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Expected</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Actual</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Variance</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $settlements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $settlement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="px-6 py-4 text-gray-900"><?php echo e($settlement->settlement_date->format('M j, Y')); ?></td>
                                <td class="px-6 py-4 text-gray-900"><?php echo e(formatCurrency($settlement->expected_amount_cents)); ?></td>
                                <td class="px-6 py-4 text-gray-900"><?php echo e(formatCurrency($settlement->net_amount_cents)); ?></td>
                                <td class="px-6 py-4 <?php echo e(abs($settlement->variance_cents) > 1 ? 'text-red-600 font-semibold' : 'text-gray-600'); ?>">
                                    <?php echo e(formatCurrency($settlement->variance_cents)); ?>

                                </td>
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
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'exceptions'): ?>
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Open Settlement Exceptions</h2>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($exceptions->isEmpty()): ?>
                <p class="px-6 py-8 text-center text-sm text-gray-500">No open exceptions.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Settlement</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Amount</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $exceptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $exception): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="px-6 py-4 text-gray-900"><?php echo e($exception->settlement->settlement_date->format('M j, Y')); ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo e($exception->exception_type->label()); ?></td>
                                <td class="px-6 py-4 text-red-600 font-medium"><?php echo e(formatCurrency($exception->amount_cents)); ?></td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-700">
                                        <?php echo e($exception->status->label()); ?>

                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="<?php echo e(route('finance.settlements.show', $exception->settlement)); ?>"
                                       class="text-indigo-600 hover:underline text-xs">Resolve</a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'invoices'): ?>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Recent Invoices</h2>
            <div class="flex gap-3">
                <a href="<?php echo e(route('finance.invoices')); ?>" class="text-sm text-indigo-600 hover:underline">View all</a>
                <a href="<?php echo e(route('finance.invoices.create')); ?>"
                   class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                    New Invoice
                </a>
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invoices->isEmpty()): ?>
                <p class="px-6 py-8 text-center text-sm text-gray-500">No invoices found.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Invoice #</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Member</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Total</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Issued</th>
                            <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="px-6 py-4 font-mono text-xs text-gray-900"><?php echo e($invoice->invoice_number); ?></td>
                                <td class="px-6 py-4 text-gray-900"><?php echo e($invoice->user->email); ?></td>
                                <td class="px-6 py-4 text-gray-900"><?php echo e($invoice->formattedTotal()); ?></td>
                                <td class="px-6 py-4">
                                    <?php $sc = match($invoice->status->value) {
                                        'DRAFT'  => 'bg-gray-100 text-gray-700',
                                        'ISSUED' => 'bg-blue-100 text-blue-700',
                                        'PAID'   => 'bg-green-100 text-green-700',
                                        'VOIDED' => 'bg-red-100 text-red-700',
                                        default  => 'bg-gray-100 text-gray-700',
                                    }; ?>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($sc); ?>">
                                        <?php echo e($invoice->status->label()); ?>

                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500"><?php echo e($invoice->issued_at?->format('M j, Y') ?? '—'); ?></td>
                                <td class="px-6 py-4">
                                    <a href="<?php echo e(route('finance.invoices.show', $invoice)); ?>"
                                       class="text-indigo-600 hover:underline text-xs">View</a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/finance/finance-dashboard.blade.php ENDPATH**/ ?>