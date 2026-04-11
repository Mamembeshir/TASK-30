<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Upgrade Membership</h1>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($error): ?>
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700"><?php echo e($error); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="rounded-lg bg-gray-50 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Current Plan</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($active): ?>
                    <p class="font-semibold text-gray-900"><?php echo e($active->plan->name); ?></p>
                    <p class="text-sm text-gray-500"><?php echo e($active->plan->tier->label()); ?></p>
                    <p class="text-sm font-medium text-gray-700"><?php echo e($active->plan->formattedPrice()); ?></p>
                <?php else: ?>
                    <p class="text-gray-500">None</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="rounded-lg bg-indigo-50 p-4">
                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wide mb-1">New Plan</p>
                <p class="font-semibold text-gray-900"><?php echo e($plan->name); ?></p>
                <p class="text-sm text-gray-500"><?php echo e($plan->tier->label()); ?></p>
                <p class="text-sm font-medium text-gray-700"><?php echo e($plan->formattedPrice()); ?></p>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-4 mb-6">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Amount Due (price difference)</span>
                <span class="text-xl font-bold text-gray-900"><?php echo e(formatCurrency($priceDiff)); ?></span>
            </div>
            <p class="mt-1 text-xs text-gray-500">Expiry date stays the same as your current plan.</p>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $confirmed): ?>
            <div class="flex gap-3">
                <a href="<?php echo e(route('membership.index')); ?>"
                   class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Back
                </a>
                <button wire:click="confirm"
                        class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Confirm Upgrade
                </button>
            </div>
        <?php else: ?>
            <div class="rounded-lg bg-yellow-50 p-4 mb-4 text-sm text-yellow-800">
                Payment processing is handled in a later step. Clicking "Place Upgrade Order" will create a pending order.
            </div>
            <button wire:click="submit"
                    wire:loading.attr="disabled"
                    class="w-full rounded-lg bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-700 disabled:opacity-50">
                <span wire:loading.remove>Place Upgrade Order</span>
                <span wire:loading>Processing…</span>
            </button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/membership/top-up-flow.blade.php ENDPATH**/ ?>