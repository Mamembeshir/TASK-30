<?php

it('formats zero cents correctly', function () {
    expect(formatCurrency(0))->toBe('$0.00');
});

it('formats positive cents correctly', function () {
    expect(formatCurrency(10000))->toBe('$100.00');
    expect(formatCurrency(99))->toBe('$0.99');
    expect(formatCurrency(150))->toBe('$1.50');
    expect(formatCurrency(100000))->toBe('$1,000.00');
});

it('formats negative cents correctly', function () {
    expect(formatCurrency(-500))->toBe('-$5.00');
    expect(formatCurrency(-1))->toBe('-$0.01');
});

it('pads pennies correctly for amounts under a dollar', function () {
    expect(formatCurrency(5))->toBe('$0.05');
    expect(formatCurrency(50))->toBe('$0.50');
});
