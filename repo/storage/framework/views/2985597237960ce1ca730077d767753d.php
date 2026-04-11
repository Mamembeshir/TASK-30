<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700">Search</label>
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Destination, specialty, title…"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Difficulty</label>
            <select wire:model.live="filterDifficulty"
                    class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">All</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $difficulties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($d->value); ?>"><?php echo e($d->label()); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Specialty</label>
            <input wire:model.live.debounce.300ms="filterSpecialty"
                   type="text"
                   placeholder="e.g. Cardiology"
                   class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trips->isEmpty()): ?>
        <p class="py-12 text-center text-gray-500">No trips match your search.</p>
    <?php else: ?>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $trips; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $trip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e(route('trips.show', $trip)); ?>"
                   class="group flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
                    <div class="flex-1 p-5">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600"><?php echo e($trip->title); ?></h3>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium
                                <?php echo e($trip->status->badgeVariant() === 'success' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                                <?php echo e($trip->status->label()); ?>

                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500"><?php echo e($trip->destination); ?></p>
                        <p class="mt-1 text-xs text-gray-400"><?php echo e($trip->specialty); ?></p>
                        <div class="mt-3 flex items-center gap-4 text-sm text-gray-600">
                            <span><?php echo e($trip->start_date->format('M j')); ?> – <?php echo e($trip->end_date->format('M j, Y')); ?></span>
                            <span><?php echo e($trip->difficulty_level->label()); ?></span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3">
                        <span class="text-sm font-medium text-gray-900"><?php echo e($trip->formattedPrice()); ?></span>
                        <span class="text-sm text-gray-500">
                            <?php echo e($trip->available_seats); ?> seat<?php echo e($trip->available_seats === 1 ? '' : 's'); ?> left
                        </span>
                    </div>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="mt-6">
            <?php echo e($trips->links()); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/trips/trip-list.blade.php ENDPATH**/ ?>