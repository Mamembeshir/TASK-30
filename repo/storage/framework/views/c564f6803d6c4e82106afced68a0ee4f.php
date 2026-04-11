<div>
    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Payment Detail','breadcrumbs' => [['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Payments','route'=>'finance.payments'],['label'=>'#'.substr($payment->id,0,8)]]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Payment Detail','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Finance','route'=>'finance.index'],['label'=>'Payments','route'=>'finance.payments'],['label'=>'#'.substr($payment->id,0,8)]])]); ?>
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
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Payment Details</h2>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">ID</dt>
                        <dd class="font-mono text-gray-900"><?php echo e($payment->id); ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Member</dt>
                        <dd class="text-gray-900"><?php echo e($payment->user->email); ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Amount</dt>
                        <dd class="text-gray-900 font-semibold"><?php echo e($payment->formattedAmount()); ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tender Type</dt>
                        <dd class="text-gray-900"><?php echo e($payment->tender_type->label()); ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd>
                            <?php $sc = match($payment->status->value) {
                                'RECORDED'  => 'bg-yellow-100 text-yellow-700',
                                'CONFIRMED' => 'bg-green-100 text-green-700',
                                'VOIDED'    => 'bg-red-100 text-red-700',
                                default     => 'bg-gray-100 text-gray-700',
                            }; ?>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($sc); ?>">
                                <?php echo e($payment->status->label()); ?>

                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Reference</dt>
                        <dd class="text-gray-900 font-mono text-xs"><?php echo e($payment->reference ?? '—'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Idempotency Key</dt>
                        <dd class="text-gray-900 font-mono text-xs break-all"><?php echo e($payment->idempotency_key); ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Confirmation Event</dt>
                        <dd class="text-gray-900 font-mono text-xs"><?php echo e($payment->confirmation_event_id ?? '—'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Created</dt>
                        <dd class="text-gray-900"><?php echo e($payment->created_at->format('M j, Y H:i')); ?></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Settlement</dt>
                        <dd class="text-gray-900">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->settlement): ?>
                                <a href="<?php echo e(route('finance.settlements.show', $payment->settlement)); ?>"
                                   class="text-indigo-600 hover:underline">
                                    <?php echo e($payment->settlement->settlement_date->format('M j, Y')); ?>

                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        
        <div class="space-y-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->status->value === 'RECORDED'): ?>
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Confirm Payment</h2>
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Confirmation Event ID (optional)</label>
                        <input type="text"
                               wire:model="confirmEventId"
                               placeholder="Auto-generated if blank"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"/>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['confirm'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mb-2 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <button wire:click="confirm"
                            wire:loading.attr="disabled"
                            class="w-full rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="confirm">Confirm</span>
                        <span wire:loading wire:target="confirm">Confirming…</span>
                    </button>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Void Payment</h2>
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
                            Void Payment
                        </button>
                    <?php else: ?>
                        <p class="text-sm text-gray-600 mb-3">This will cancel the payment and any linked signups or membership orders. Confirm?</p>
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
<?php /**PATH /var/www/html/resources/views/livewire/finance/payment-detail.blade.php ENDPATH**/ ?>