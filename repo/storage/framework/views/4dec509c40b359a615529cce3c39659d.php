<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e($title ?? config('app.name', 'MedVoyage')); ?></title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>
<body class="h-full bg-[#F1F4F8] font-sans antialiased">

<div class="min-h-full flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
        
        <div class="flex flex-col items-center mb-8">
            <div class="w-12 h-12 rounded-xl bg-[#1B6B93] flex items-center justify-center mb-4 shadow-md">
                <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 6v4m0 4h.01"/>
                </svg>
            </div>
            <h1 class="font-display text-2xl font-semibold text-[#111827]">MedVoyage</h1>
            <p class="text-sm text-[#9CA3AF] mt-1">Provider & Trip Enrollment System</p>
        </div>

        <?php echo e($slot); ?>

    </div>
</div>

<?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>
</html>
<?php /**PATH /var/www/html/resources/views/layouts/guest.blade.php ENDPATH**/ ?>