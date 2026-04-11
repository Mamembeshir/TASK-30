<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">
            <?php echo e($review?->exists ? 'Edit Your Review' : 'Write a Review'); ?>

        </h1>
        <p class="mt-1 text-sm text-gray-500"><?php echo e($trip->title); ?></p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 space-y-6">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Rating <span class="text-red-500">*</span>
            </label>
            <div class="flex gap-1"
                 x-data="{ hovered: 0, selected: <?php if ((object) ('rating') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('rating'->value()); ?>')<?php echo e('rating'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('rating'); ?>')<?php endif; ?> }">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 1; $i <= 5; $i++): ?>
                    <button type="button"
                            wire:click="setRating(<?php echo e($i); ?>)"
                            x-on:mouseenter="hovered = <?php echo e($i); ?>"
                            x-on:mouseleave="hovered = 0"
                            class="text-3xl leading-none transition-colors focus:outline-none"
                            :class="(hovered || selected) >= <?php echo e($i); ?> ? 'text-amber-400' : 'text-gray-300'"
                            aria-label="<?php echo e($i); ?> star<?php echo e($i > 1 ? 's' : ''); ?>">
                        ★
                    </button>
                <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span class="ml-3 self-center text-sm text-gray-500"
                      x-text="selected ? selected + (selected === 1 ? ' star' : ' stars') : 'Select a rating'">
                </span>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['rating'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Review
                <span class="text-gray-400 font-normal">(optional)</span>
            </label>
            <div x-data="{ chars: <?php echo e(strlen($reviewText)); ?> }">
                <textarea wire:model="reviewText"
                          x-on:input="chars = $event.target.value.length"
                          rows="5"
                          maxlength="2000"
                          placeholder="Share your experience…"
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm resize-none"></textarea>
                <p class="mt-1 text-right text-xs"
                   :class="chars > 1900 ? 'text-red-500' : 'text-gray-400'">
                    <span x-text="chars"></span>/2000
                </p>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['reviewText'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="flex items-center justify-between pt-2">
            <a href="<?php echo e(route('trips.show', $trip)); ?>"
               class="text-sm text-gray-500 hover:text-gray-700">
                &larr; Cancel
            </a>
            <button wire:click="submit"
                    wire:loading.attr="disabled"
                    class="rounded-lg bg-indigo-600 px-6 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                <span wire:loading.remove><?php echo e($review?->exists ? 'Update Review' : 'Submit Review'); ?></span>
                <span wire:loading>Saving…</span>
            </button>
        </div>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/reviews/review-form.blade.php ENDPATH**/ ?>