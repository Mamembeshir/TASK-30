<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step < 4): ?>
        
        <div class="mb-8">
            <div class="flex items-center justify-between text-sm">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['Review', 'Details', 'Payment']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold
                            <?php echo e($step > $i + 1 ? 'bg-indigo-600 text-white' : ($step === $i + 1 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500')); ?>">
                            <?php echo e($i + 1); ?>

                        </span>
                        <span class="<?php echo e($step === $i + 1 ? 'font-semibold text-indigo-600' : 'text-gray-400'); ?>"><?php echo e($label); ?></span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i < 2): ?> <div class="flex-1 border-t border-gray-200 mx-3"></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="mb-6 rounded-lg bg-amber-50 p-3 text-center text-sm text-amber-800"
             x-data="{ secs: <?php echo e($holdSecondsRemaining); ?> }"
             x-init="setInterval(() => { if (secs > 0) secs-- }, 1000)">
            <span x-show="secs > 120">Hold expires in <span x-text="Math.floor(secs/60) + ':' + String(secs%60).padStart(2,'0')"></span></span>
            <span x-show="secs <= 120 && secs > 0" class="font-semibold text-red-700">
                Hurry! Hold expires in <span x-text="secs"></span> seconds
            </span>
            <span x-show="secs <= 0" class="font-semibold text-red-700">Your hold has expired.</span>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 1): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="mb-4 text-lg font-semibold">Review Your Trip</h2>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-500">Trip</dt><dd class="font-medium"><?php echo e($trip->title); ?></dd></div>
                <div><dt class="text-gray-500">Destination</dt><dd class="font-medium"><?php echo e($trip->destination); ?></dd></div>
                <div><dt class="text-gray-500">Dates</dt><dd class="font-medium"><?php echo e($trip->start_date->format('M j')); ?> – <?php echo e($trip->end_date->format('M j, Y')); ?></dd></div>
                <div><dt class="text-gray-500">Price</dt><dd class="font-medium"><?php echo e($trip->formattedPrice()); ?></dd></div>
            </dl>
        </div>
        <div class="mt-6 flex justify-end">
            <button wire:click="nextStep"
                    class="rounded-lg bg-indigo-600 px-6 py-2 font-medium text-white hover:bg-indigo-700">
                Continue
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 2): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold">Emergency Contact</h2>
            <div>
                <label class="block text-sm font-medium text-gray-700">Contact Name <span class="text-red-500">*</span></label>
                <input wire:model="emergencyContactName" type="text"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['emergencyContactName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Contact Phone <span class="text-red-500">*</span></label>
                <input wire:model="emergencyContactPhone" type="tel"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['emergencyContactPhone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Dietary Requirements</label>
                <input wire:model="dietaryRequirements" type="text"
                       placeholder="None, vegetarian, etc."
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
        </div>
        <div class="mt-6 flex justify-between">
            <button wire:click="prevStep"
                    class="rounded-lg border border-gray-300 px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back
            </button>
            <button wire:click="nextStep"
                    class="rounded-lg bg-indigo-600 px-6 py-2 font-medium text-white hover:bg-indigo-700">
                Continue
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 3): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <h2 class="text-lg font-semibold">Payment</h2>
            <p class="text-sm text-gray-500">Select your payment method. Finance staff will confirm receipt.</p>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tender Type <span class="text-red-500">*</span></label>
                <select wire:model="tenderType"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="CASH">Cash</option>
                    <option value="CHECK">Check</option>
                    <option value="CARD_ON_FILE">Card on File</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Reference Number</label>
                <input wire:model="referenceNumber" type="text"
                       placeholder="Check # or transaction ID"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                <span class="text-sm text-gray-700">Amount Due</span>
                <span class="text-lg font-bold text-gray-900"><?php echo e($trip->formattedPrice()); ?></span>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['payment'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['hold'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="mt-6 flex justify-between">
            <button wire:click="prevStep"
                    class="rounded-lg border border-gray-300 px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back
            </button>
            <button wire:click="submitPayment"
                    wire:loading.attr="disabled"
                    class="rounded-lg bg-green-600 px-6 py-2 font-medium text-white hover:bg-green-700 disabled:opacity-50">
                <span wire:loading.remove>Confirm Booking</span>
                <span wire:loading>Processing…</span>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 4): ?>
        <div class="rounded-xl border border-green-200 bg-green-50 p-8 text-center">
            <svg class="mx-auto mb-4 h-12 w-12 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <h2 class="text-xl font-bold text-green-800">Booking Confirmed!</h2>
            <p class="mt-2 text-sm text-green-700">
                Your signup for <strong><?php echo e($trip->title); ?></strong> has been recorded.
                Finance staff will confirm your payment shortly.
            </p>
            <div class="mt-6 flex justify-center gap-4">
                <a href="<?php echo e(route('my-signups')); ?>"
                   class="rounded-lg bg-indigo-600 px-6 py-2 font-medium text-white hover:bg-indigo-700">
                    View My Bookings
                </a>
                <a href="<?php echo e(route('trips.index')); ?>"
                   class="rounded-lg border border-gray-300 px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Browse More Trips
                </a>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/trips/signup-wizard.blade.php ENDPATH**/ ?>