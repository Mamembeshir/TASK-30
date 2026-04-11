<div class="space-y-8">
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($waitlistEntries->where('status.value', 'OFFERED')->isNotEmpty()): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="mb-3 font-semibold text-amber-800">Seat Offers — Act Now!</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $waitlistEntries->where('status.value', 'OFFERED'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="flex items-center justify-between rounded-lg bg-white px-4 py-3 shadow-sm mb-2">
                    <div>
                        <p class="font-medium text-gray-900"><?php echo e($entry->trip->title); ?></p>
                        <p class="text-xs text-amber-600">
                            Expires at <?php echo e($entry->offer_expires_at?->format('H:i')); ?>

                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="acceptOffer('<?php echo e($entry->id); ?>')"
                                class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">
                            Accept
                        </button>
                        <button wire:click="declineOffer('<?php echo e($entry->id); ?>')"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Decline
                        </button>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div>
        <h2 class="mb-4 text-lg font-semibold text-gray-900">My Bookings</h2>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($signups->isEmpty()): ?>
            <p class="text-sm text-gray-500">You have no bookings yet. <a href="<?php echo e(route('trips.index')); ?>" class="text-indigo-600 hover:underline">Browse trips</a>.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $signups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $signup): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-4">
                        <div>
                            <a href="<?php echo e(route('trips.show', $signup->trip)); ?>"
                               class="font-medium text-gray-900 hover:text-indigo-600">
                                <?php echo e($signup->trip->title); ?>

                            </a>
                            <p class="text-sm text-gray-500">
                                <?php echo e($signup->trip->start_date->format('M j, Y')); ?> &middot; <?php echo e($signup->trip->destination); ?>

                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold
                                <?php if($signup->status->value === 'CONFIRMED'): ?> bg-green-100 text-green-700
                                <?php elseif($signup->status->value === 'HOLD'): ?> bg-amber-100 text-amber-700
                                <?php elseif($signup->status->value === 'CANCELLED'): ?> bg-red-100 text-red-700
                                <?php else: ?> bg-gray-100 text-gray-600 <?php endif; ?>">
                                <?php echo e($signup->status->label()); ?>

                            </span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($signup->status->value === 'HOLD'): ?>
                                <button wire:click="cancelSignup('<?php echo e($signup->id); ?>')"
                                        wire:confirm="Cancel this hold?"
                                        class="text-xs text-red-600 hover:underline">
                                    Cancel Hold
                                </button>
                            <?php elseif($signup->status->value === 'CONFIRMED'): ?>
                                <button wire:click="cancelSignup('<?php echo e($signup->id); ?>')"
                                        wire:confirm="Cancel this confirmed booking? This may result in a refund request."
                                        class="text-xs text-red-600 hover:underline">
                                    Cancel
                                </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($waitlistEntries->isNotEmpty()): ?>
        <div>
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Waitlist</h2>
            <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $waitlistEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entry->status->value !== 'OFFERED'): ?>
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-4">
                            <div>
                                <p class="font-medium text-gray-900"><?php echo e($entry->trip->title); ?></p>
                                <p class="text-sm text-gray-500">Position #<?php echo e($entry->position); ?></p>
                            </div>
                            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                                <?php echo e($entry->status->label()); ?>

                            </span>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['cancel'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-2 text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['offer'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>  <p class="mt-2 text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/trips/my-signups.blade.php ENDPATH**/ ?>