<?php

use App\Services\EncryptionService;

beforeEach(function () {
    $this->service = new EncryptionService();
});

it('encrypts and decrypts a string round-trip', function () {
    $original  = 'sensitive-data-123';
    $encrypted = $this->service->encrypt($original);

    expect($encrypted)->not->toBe($original);
    expect($this->service->decrypt($encrypted))->toBe($original);
});

it('returns null for null input', function () {
    expect($this->service->encrypt(null))->toBeNull();
    expect($this->service->decrypt(null))->toBeNull();
});

it('returns null for empty string input', function () {
    expect($this->service->encrypt(''))->toBeNull();
    expect($this->service->decrypt(''))->toBeNull();
});

it('masks SSN correctly', function () {
    expect($this->service->applyMask('123-45-6789', 'ssn'))->toBe('***-**-6789');
});

it('masks phone correctly', function () {
    expect($this->service->applyMask('555-867-5309', 'phone'))->toBe('***-***-5309');
});

it('masks license plate keeping last 4', function () {
    expect($this->service->applyMask('ABC12345', 'license'))->toBe('****2345');
});

it('masks email correctly', function () {
    expect($this->service->applyMask('john@example.com', 'email'))->toBe('j***@example.com');
});
