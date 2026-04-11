<div>
    <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Search Trips','breadcrumbs' => [['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Search']]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Search Trips','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([['label'=>'Dashboard','route'=>'dashboard'],['label'=>'Search']])]); ?>
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

    <div class="flex gap-6 items-start">

        
        <aside class="w-64 flex-shrink-0 space-y-5">

            
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Filters</h2>
                    <button wire:click="resetFilters"
                            class="text-xs text-indigo-600 hover:underline">Reset</button>
                </div>

                
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Specialty</label>
                    <input type="text"
                           wire:model.live.debounce.300ms="filterSpecialty"
                           placeholder="e.g. Cardiology"
                           class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                </div>

                
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Start Date From</label>
                    <input type="date"
                           wire:model.live="filterDateFrom"
                           class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">End Date By</label>
                    <input type="date"
                           wire:model.live="filterDateTo"
                           class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                </div>

                
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-2">Difficulty</label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $difficulties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="flex items-center gap-2 mb-1 text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox"
                                   wire:model.live="filterDifficulties"
                                   value="<?php echo e($d->value); ?>"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"/>
                            <?php echo e($d->label()); ?>

                        </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        Duration (days)
                    </label>
                    <div class="flex gap-2 items-center">
                        <input type="number" min="1" max="365"
                               wire:model.live.debounce.400ms="filterDurationMin"
                               placeholder="Min"
                               class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                        <span class="text-gray-400 text-xs">–</span>
                        <input type="number" min="1" max="365"
                               wire:model.live.debounce.400ms="filterDurationMax"
                               placeholder="Max"
                               class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                    </div>
                </div>

                
                <div>
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox"
                               wire:model.live="filterPrerequisites"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"/>
                        Has prerequisites
                    </label>
                </div>
            </div>

            
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <button wire:click="$toggle('showHistory')"
                        class="w-full flex items-center justify-between text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                    <span>Search History</span>
                    <svg class="w-4 h-4 transition-transform <?php echo e($showHistory ? 'rotate-180' : ''); ?>"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showHistory): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($history->isEmpty()): ?>
                        <p class="text-xs text-gray-400 mt-2">No recent searches.</p>
                    <?php else: ?>
                        <ul class="mt-2 space-y-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li>
                                    <button wire:click="selectSuggestion('<?php echo e(addslashes($h->query)); ?>')"
                                            class="text-sm text-indigo-600 hover:underline truncate block w-full text-left">
                                        <?php echo e($h->query); ?>

                                    </button>
                                </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </ul>
                        <button wire:click="clearHistory"
                                class="mt-3 text-xs text-red-500 hover:underline">
                            Clear History
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </aside>

        
        <div class="flex-1 min-w-0">

            
            <div class="mb-4" x-data="{ open: false }" @click.away="open = false">
                <div class="flex gap-3">
                    <div class="relative flex-1">
                        <input type="text"
                               wire:model.live.debounce.300ms="query"
                               @focus="open = true"
                               @input="open = true"
                               placeholder="Search by title, specialty, destination, doctor…"
                               class="w-full rounded-xl border border-gray-300 pl-4 pr-10 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </span>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($typeAheadResults)): ?>
                            <ul x-show="open"
                                class="absolute z-30 left-0 right-0 top-full mt-1 rounded-xl border border-gray-200 bg-white shadow-lg divide-y divide-gray-50 text-sm">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $typeAheadResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $suggestion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li>
                                        <button type="button"
                                                wire:click="selectSuggestion('<?php echo e(addslashes($suggestion['term'])); ?>')"
                                                @click="open = false"
                                                class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 text-left">
                                            <span class="text-gray-800"><?php echo e($suggestion['term']); ?></span>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($suggestion['category']): ?>
                                                <span class="text-xs text-gray-400 ml-2"><?php echo e($suggestion['category']); ?></span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </button>
                                    </li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </ul>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <select wire:model.live="sort"
                            class="rounded-xl border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sortOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filterSpecialty || $filterDateFrom || $filterDateTo || !empty($filterDifficulties) || $filterDurationMin || $filterDurationMax || $filterPrerequisites): ?>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filterSpecialty): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                <?php echo e($filterSpecialty); ?>

                                <button wire:click="$set('filterSpecialty', '')" class="hover:text-indigo-900">×</button>
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filterDateFrom): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                From <?php echo e($filterDateFrom); ?>

                                <button wire:click="$set('filterDateFrom', '')" class="hover:text-indigo-900">×</button>
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filterDateTo): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                To <?php echo e($filterDateTo); ?>

                                <button wire:click="$set('filterDateTo', '')" class="hover:text-indigo-900">×</button>
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $filterDifficulties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                <?php echo e($d); ?>

                                <button wire:click="$set('filterDifficulties', array_diff(filterDifficulties, ['<?php echo e($d); ?>']))" class="hover:text-indigo-900">×</button>
                            </span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filterDurationMin || $filterDurationMax): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                <?php echo e($filterDurationMin ?: '1'); ?>–<?php echo e($filterDurationMax ?: '∞'); ?> days
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filterPrerequisites): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs text-indigo-700">
                                Has prerequisites
                                <button wire:click="$set('filterPrerequisites', false)" class="hover:text-indigo-900">×</button>
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <p class="mb-4 text-sm text-gray-500">
                <?php echo e(number_format($trips->total())); ?> trip<?php echo e($trips->total() !== 1 ? 's' : ''); ?> found
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($query): ?> for <strong class="text-gray-700">"<?php echo e($query); ?>"</strong> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trips->isEmpty()): ?>
                <div class="rounded-xl border border-gray-200 bg-white p-12 text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <p class="text-sm text-gray-500">No trips match your search. Try adjusting the filters.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $trips; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $trip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('trips.show', $trip)); ?>"
                           class="group flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
                            <div class="flex-1 p-5">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600 line-clamp-2">
                                        <?php echo e($trip->title); ?>

                                    </h3>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium
                                        <?php echo e($trip->status->badgeVariant() === 'success' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                                        <?php echo e($trip->status->label()); ?>

                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500"><?php echo e($trip->destination); ?></p>
                                <p class="mt-1 text-xs text-gray-400"><?php echo e($trip->specialty); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trip->doctor?->user): ?>
                                    <p class="mt-1 text-xs text-gray-400">
                                        Dr. <?php echo e($trip->doctor->user->username); ?>

                                    </p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="mt-3 flex items-center gap-4 text-sm text-gray-600">
                                    <span><?php echo e($trip->start_date->format('M j')); ?>–<?php echo e($trip->end_date->format('M j, Y')); ?></span>
                                    <span class="rounded-full px-1.5 py-0.5 text-xs
                                        <?php echo e($trip->difficulty_level->badgeVariant() === 'success' ? 'bg-green-50 text-green-700' :
                                           ($trip->difficulty_level->badgeVariant() === 'warning' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700')); ?>">
                                        <?php echo e($trip->difficulty_level->label()); ?>

                                    </span>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trip->average_rating): ?>
                                    <p class="mt-1 text-xs text-amber-600">★ <?php echo e(number_format($trip->average_rating, 1)); ?></p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3">
                                <span class="text-sm font-semibold text-gray-900"><?php echo e($trip->formattedPrice()); ?></span>
                                <span class="text-xs text-gray-500">
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
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/search/trip-search.blade.php ENDPATH**/ ?>