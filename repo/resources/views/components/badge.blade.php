@props([
    'variant' => 'neutral',  // success | warning | danger | info | neutral
    'status'  => null,       // auto-map status string → variant
])

@php
    // Auto-map common status strings to variants
    if ($status !== null) {
        $statusMap = [
            // Success
            'active'      => 'success', 'approved'    => 'success', 'completed'   => 'success',
            'confirmed'   => 'success', 'paid'        => 'success', 'published'   => 'success',
            'processed'   => 'success', 'reconciled'  => 'success',
            // Warning
            'pending'     => 'warning', 'under_review' => 'warning', 'hold'       => 'warning',
            'full'        => 'warning', 'offered'     => 'warning', 'issued'      => 'warning',
            'recorded'    => 'warning', 'more_materials_requested' => 'warning',
            'partially_refunded' => 'warning',
            // Danger
            'rejected'    => 'danger',  'voided'      => 'danger',  'expired'     => 'danger',
            'cancelled'   => 'danger',  'suspended'   => 'danger',  'deactivated' => 'danger',
            'removed'     => 'danger',  'exception'   => 'danger',
            // Info
            'submitted'   => 'info',    'waiting'     => 'info',    'initial_review' => 'info',
            // Neutral
            'draft'       => 'neutral', 'closed'      => 'neutral', 'declined'    => 'neutral',
            'not_submitted' => 'neutral', 'written_off' => 'neutral',
        ];
        $variant = $statusMap[strtolower($status)] ?? 'neutral';
    }

    $classes = match($variant) {
        'success' => 'text-[#0F766E] bg-[#ECFDF5]',
        'warning' => 'text-[#B45309] bg-[#FFFBEB]',
        'danger'  => 'text-[#B91C1C] bg-[#FEF2F2]',
        'info'    => 'text-[#1D4ED8] bg-[#EFF6FF]',
        default   => 'text-[#4B5563] bg-[#F1F4F8]',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {$classes}"]) }}>
    {{ $slot->isEmpty() && $status ? ucwords(strtolower(str_replace('_', ' ', $status))) : $slot }}
</span>
