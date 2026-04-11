<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Review Moderation</h1>
        <div>
            <label class="text-sm text-gray-600 mr-2">Status</label>
            <select wire:model.live="filterStatus"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($status->value); ?>"><?php echo e($status->label()); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['action'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700"><?php echo e($message); ?></div>
    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reviews->isEmpty()): ?>
        <p class="py-12 text-center text-gray-500">No reviews found.</p>
    <?php else: ?>
        <div class="space-y-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $reviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $review): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="rounded-xl border bg-white p-5
                    <?php if($review->status->value === 'FLAGGED'): ?> border-amber-300 bg-amber-50
                    <?php elseif($review->status->value === 'REMOVED'): ?> border-gray-200 opacity-60
                    <?php else: ?> border-gray-200 <?php endif; ?>">

                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="font-medium text-gray-900"><?php echo e($review->user?->name); ?></span>
                                <span class="text-gray-400">&rarr;</span>
                                <a href="<?php echo e(route('trips.show', $review->trip)); ?>"
                                   class="text-indigo-600 hover:underline truncate">
                                    <?php echo e($review->trip->title); ?>

                                </a>
                                <span class="text-amber-400">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 1; $i <= 5; $i++): ?><?php echo e($i <= $review->rating ? '★' : '☆'); ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                    <?php if($review->status->value === 'ACTIVE'): ?> bg-green-100 text-green-700
                                    <?php elseif($review->status->value === 'FLAGGED'): ?> bg-amber-100 text-amber-700
                                    <?php else: ?> bg-gray-100 text-gray-500 <?php endif; ?>">
                                    <?php echo e($review->status->label()); ?>

                                </span>
                                <span class="text-gray-400 text-xs"><?php echo e($review->created_at->format('M j, Y')); ?></span>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review->review_text): ?>
                                <p class="mt-2 text-sm text-gray-700 line-clamp-3"><?php echo e($review->review_text); ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        
                        <div class="flex shrink-0 gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review->status->value === 'ACTIVE'): ?>
                                <button wire:click="flag('<?php echo e($review->id); ?>')"
                                        wire:confirm="Flag this review? It will be hidden from the trip page."
                                        class="rounded px-3 py-1 text-xs font-medium bg-amber-100 text-amber-700 hover:bg-amber-200">
                                    Flag
                                </button>
                                <button wire:click="remove('<?php echo e($review->id); ?>')"
                                        wire:confirm="Permanently remove this review?"
                                        class="rounded px-3 py-1 text-xs font-medium bg-red-100 text-red-700 hover:bg-red-200">
                                    Remove
                                </button>
                            <?php elseif($review->status->value === 'FLAGGED'): ?>
                                <button wire:click="restore('<?php echo e($review->id); ?>')"
                                        class="rounded px-3 py-1 text-xs font-medium bg-green-100 text-green-700 hover:bg-green-200">
                                    Restore
                                </button>
                                <button wire:click="remove('<?php echo e($review->id); ?>')"
                                        wire:confirm="Permanently remove this review?"
                                        class="rounded px-3 py-1 text-xs font-medium bg-red-100 text-red-700 hover:bg-red-200">
                                    Remove
                                </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="mt-6"><?php echo e($reviews->links()); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/reviews/review-moderation.blade.php ENDPATH**/ ?>