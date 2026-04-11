<?php

if (! function_exists('formatCurrency')) {
    /**
     * Format integer cents to a display currency string.
     *
     * Examples:
     *   formatCurrency(10000)  → "$100.00"
     *   formatCurrency(0)      → "$0.00"
     *   formatCurrency(-500)   → "-$5.00"
     *   formatCurrency(99)     → "$0.99"
     */
    function formatCurrency(int $cents, string $symbol = '$'): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $dollars  = intdiv($absolute, 100);
        $pennies  = $absolute % 100;
        $formatted = $symbol . number_format($dollars) . '.' . str_pad($pennies, 2, '0', STR_PAD_LEFT);
        return $negative ? '-' . $formatted : $formatted;
    }
}

if (! function_exists('centsFromDollars')) {
    /**
     * Convert a dollar string (e.g. "10.99") to integer cents (1099).
     * Uses string parsing to avoid floating-point issues.
     */
    function centsFromDollars(string|float|int $dollars): int
    {
        // Strip non-numeric except dot and minus
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $dollars);
        $parts  = explode('.', $clean, 2);
        $whole  = (int) ($parts[0] ?? 0);
        $frac   = str_pad(substr($parts[1] ?? '0', 0, 2), 2, '0', STR_PAD_RIGHT);
        $abs    = abs($whole) * 100 + (int) $frac;
        return $whole < 0 ? -$abs : $abs;
    }
}

if (! function_exists('correlationId')) {
    /**
     * Get or generate the current request's correlation ID.
     * Uses the X-Correlation-ID header if present, otherwise generates a UUID.
     */
    function correlationId(): string
    {
        static $id = null;
        if ($id === null) {
            $id = request()?->header('X-Correlation-ID') ?? (string) \Illuminate\Support\Str::uuid();
        }
        return $id;
    }
}
