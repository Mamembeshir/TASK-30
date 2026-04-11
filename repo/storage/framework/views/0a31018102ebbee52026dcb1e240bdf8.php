<div>
    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Invoice ' . $invoice->invoice_number,'breadcrumbs' => [['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Invoices','route'=>'finance.invoices'],['label'=>$invoice->invoice_number]]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Invoice ' . $invoice->invoice_number),'breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Invoices','route'=>'finance.invoices'],['label'=>$invoice->invoice_number]])]); ?>
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

    <div class="grid gap-6 lg:grid-cols-3">
        
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 font-mono"><?php echo e($invoice->invoice_number); ?></h2>
                        <p class="text-sm text-gray-500 mt-1"><?php echo e($invoice->user->email); ?></p>
                    </div>
                    <?php $sc = match($invoice->status->value) {
                        'DRAFT'  => 'bg-gray-100 text-gray-700',
                        'ISSUED' => 'bg-blue-100 text-blue-700',
                        'PAID'   => 'bg-green-100 text-green-700',
                        'VOIDED' => 'bg-red-100 text-red-700',
                        default  => 'bg-gray-100 text-gray-700',
                    }; ?>
                    <span class="rounded-full px-3 py-1 text-sm font-semibold <?php echo e($sc); ?>">
                        <?php echo e($invoice->status->label()); ?>

                    </span>
                </div>

                <dl class="grid grid-cols-2 gap-4 text-sm mb-6">
                    <div>
                        <dt class="text-gray-500">Issued</dt>
                        <dd class="text-gray-900"><?php echo e($invoice->issued_at?->format('M j, Y') ?? '—'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Due</dt>
                        <dd class="text-gray-900"><?php echo e($invoice->due_date?->format('M j, Y') ?? '—'); ?></dd>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invoice->notes): ?>
                        <div class="col-span-2">
                            <dt class="text-gray-500">Notes</dt>
                            <dd class="text-gray-900"><?php echo e($invoice->notes); ?></dd>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </dl>

                
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Description</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Type</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $lineItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td class="px-4 py-3 text-gray-900"><?php echo e($item->description); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo e($item->line_type->label()); ?></td>
                                <td class="px-4 py-3 text-right text-gray-900"><?php echo e(formatCurrency($item->amount_cents)); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">No line items.</td>
                            </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="2" class="px-4 py-3 text-right text-gray-700">Total</td>
                            <td class="px-4 py-3 text-right text-gray-900"><?php echo e($invoice->formattedTotal()); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="space-y-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invoice->status->value === 'ISSUED'): ?>
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Mark as Paid</h2>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['paid'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mb-2 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <button wire:click="markPaid"
                            wire:loading.attr="disabled"
                            class="w-full rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="markPaid">Mark Paid</span>
                        <span wire:loading wire:target="markPaid">Updating…</span>
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($invoice->status->value, ['DRAFT', 'ISSUED'])): ?>
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Void Invoice</h2>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['void'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mb-2 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$showVoidConfirm): ?>
                        <button wire:click="$set('showVoidConfirm', true)"
                                class="w-full rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Void Invoice
                        </button>
                    <?php else: ?>
                        <p class="text-sm text-gray-600 mb-3">This cannot be undone. Confirm?</p>
                        <div class="flex gap-2">
                            <button wire:click="$set('showVoidConfirm', false)"
                                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button wire:click="void"
                                    wire:loading.attr="disabled"
                                    class="flex-1 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="void">Void</span>
                                <span wire:loading wire:target="void">Voiding…</span>
                            </button>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/finance/invoice-detail.blade.php ENDPATH**/ ?>