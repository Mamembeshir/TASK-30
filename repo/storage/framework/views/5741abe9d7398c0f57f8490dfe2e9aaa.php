<div>
    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Settlement '.e($settlement->settlement_date->format('M j, Y')).'','breadcrumbs' => [['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Settlements','route'=>'finance.settlements'],['label'=>$settlement->settlement_date->format('M j, Y')]]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Settlement '.e($settlement->settlement_date->format('M j, Y')).'','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Settlements','route'=>'finance.settlements'],['label'=>$settlement->settlement_date->format('M j, Y')]])]); ?>
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

    
    <div class="rounded-xl border border-gray-200 bg-white p-6 mb-6">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900"><?php echo e($settlement->settlement_date->format('F j, Y')); ?></h2>
                <p class="text-sm text-gray-500 mt-1">Settlement summary</p>
            </div>
            <div class="flex gap-3">
                <button wire:click="downloadStatement"
                        wire:loading.attr="disabled"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                    <span wire:loading.remove wire:target="downloadStatement">Download CSV</span>
                    <span wire:loading wire:target="downloadStatement">Preparing…</span>
                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($settlement->status->value !== 'RECONCILED' && $exceptions->where('status.value', 'OPEN')->isEmpty()): ?>
                    <button wire:click="reReconcile"
                            wire:loading.attr="disabled"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="reReconcile">Re-Reconcile</span>
                        <span wire:loading wire:target="reReconcile">Processing…</span>
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['reconcile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-2 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <dl class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4 text-sm">
            <div class="rounded-lg bg-gray-50 p-4">
                <dt class="text-gray-500 text-xs">Expected</dt>
                <dd class="text-gray-900 font-semibold text-lg mt-1"><?php echo e(formatCurrency($settlement->expected_amount_cents)); ?></dd>
            </div>
            <div class="rounded-lg bg-gray-50 p-4">
                <dt class="text-gray-500 text-xs">Actual</dt>
                <dd class="text-gray-900 font-semibold text-lg mt-1"><?php echo e(formatCurrency($settlement->net_amount_cents)); ?></dd>
            </div>
            <div class="rounded-lg <?php echo e(abs($settlement->variance_cents) > 1 ? 'bg-red-50' : 'bg-green-50'); ?> p-4">
                <dt class="<?php echo e(abs($settlement->variance_cents) > 1 ? 'text-red-500' : 'text-green-500'); ?> text-xs">Variance</dt>
                <dd class="<?php echo e(abs($settlement->variance_cents) > 1 ? 'text-red-700' : 'text-green-700'); ?> font-semibold text-lg mt-1">
                    <?php echo e(formatCurrency($settlement->variance_cents)); ?>

                </dd>
            </div>
            <div class="rounded-lg bg-gray-50 p-4">
                <dt class="text-gray-500 text-xs">Status</dt>
                <dd class="mt-1">
                    <?php $sc = match($settlement->status->value) {
                        'OPEN'       => 'bg-yellow-100 text-yellow-700',
                        'RECONCILED' => 'bg-green-100 text-green-700',
                        'EXCEPTION'  => 'bg-red-100 text-red-700',
                        default      => 'bg-gray-100 text-gray-700',
                    }; ?>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($sc); ?>">
                        <?php echo e($settlement->status->label()); ?>

                    </span>
                </dd>
            </div>
        </dl>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($exceptions->isNotEmpty()): ?>
        <div class="rounded-xl border border-red-200 bg-white overflow-hidden mb-6">
            <div class="px-6 py-4 bg-red-50 border-b border-red-200">
                <h2 class="text-sm font-semibold text-red-800">Settlement Exceptions (<?php echo e($exceptions->count()); ?>)</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Type</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Variance</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Notes</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $exceptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $exception): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="px-6 py-4 text-gray-900"><?php echo e($exception->exception_type->label()); ?></td>
                            <td class="px-6 py-4 text-red-600 font-medium"><?php echo e(formatCurrency($exception->amount_cents)); ?></td>
                            <td class="px-6 py-4">
                                <?php $sc = match($exception->status->value) {
                                    'OPEN'        => 'bg-red-100 text-red-700',
                                    'RESOLVED'    => 'bg-green-100 text-green-700',
                                    'WRITTEN_OFF' => 'bg-gray-100 text-gray-700',
                                    default       => 'bg-gray-100 text-gray-700',
                                }; ?>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($sc); ?>">
                                    <?php echo e($exception->status->label()); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600 max-w-xs truncate"><?php echo e($exception->notes ?? '—'); ?></td>
                            <td class="px-6 py-4">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($exception->status->value === 'OPEN'): ?>
                                    <button wire:click="$set('resolveExceptionId', '<?php echo e($exception->id); ?>')"
                                            class="text-indigo-600 hover:underline text-xs">Resolve</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolveExceptionId === $exception->id): ?>
                            <tr class="bg-indigo-50">
                                <td colspan="5" class="px-6 py-4">
                                    <div class="max-w-lg space-y-3">
                                        <h3 class="text-sm font-semibold text-gray-800">Resolve Exception</h3>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['resolve'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Resolution Type</label>
                                            <select wire:model="resolutionType"
                                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                <option value="">Select…</option>
                                                <option value="RESOLVED">Resolved</option>
                                                <option value="WRITTEN_OFF">Written Off</option>
                                            </select>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['resolutionType'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Notes (min 5 chars)</label>
                                            <textarea wire:model="resolutionNote" rows="2"
                                                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['resolutionNote'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="$set('resolveExceptionId', null)"
                                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                                                Cancel
                                            </button>
                                            <button wire:click="resolveException"
                                                    wire:loading.attr="disabled"
                                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                                <span wire:loading.remove wire:target="resolveException">Save</span>
                                                <span wire:loading wire:target="resolveException">Saving…</span>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">Payments in Settlement (<?php echo e($payments->count()); ?>)</h2>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payments->isEmpty()): ?>
            <p class="px-6 py-8 text-center text-sm text-gray-500">No payments linked to this settlement.</p>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">ID</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Member</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Amount</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Tender</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="px-6 py-4 font-mono text-xs text-gray-500">
                                <a href="<?php echo e(route('finance.payments.show', $payment)); ?>" class="text-indigo-600 hover:underline">
                                    <?php echo e(substr($payment->id, 0, 8)); ?>

                                </a>
                            </td>
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
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/finance/settlement-detail.blade.php ENDPATH**/ ?>