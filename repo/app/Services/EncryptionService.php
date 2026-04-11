<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class EncryptionService
{
    /**
     * Encrypt a plaintext string for storage.
     * Returns null if input is null/empty.
     */
    public function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }
        return Crypt::encryptString($plaintext);
    }

    /**
     * Decrypt a ciphertext string.
     * Returns null if input is null/empty or decryption fails.
     */
    public function decrypt(?string $ciphertext): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return null;
        }
        try {
            return Crypt::decryptString($ciphertext);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Encrypt and build a display mask in one call.
     * Returns ['encrypted' => '...', 'mask' => '...']
     */
    public function encryptWithMask(string $plaintext, string $maskPattern): array
    {
        return [
            'encrypted' => $this->encrypt($plaintext),
            'mask'       => $this->applyMask($plaintext, $maskPattern),
        ];
    }

    /**
     * Apply a masking pattern to a string.
     *
     * Patterns:
     *  - 'ssn'     → "***-**-1234"
     *  - 'phone'   → "***-***-4567"
     *  - 'license' → "****1234"
     *  - 'address' → "*** *** Street, City, **"
     *  - 'email'   → "j***@example.com"
     */
    public function applyMask(string $value, string $pattern): string
    {
        return match ($pattern) {
            'ssn'     => $this->maskSsn($value),
            'phone'   => $this->maskPhone($value),
            'license' => $this->maskLicense($value),
            'address' => $this->maskAddress($value),
            'email'   => $this->maskEmail($value),
            default   => $this->maskDefault($value),
        };
    }

    private function maskSsn(string $ssn): string
    {
        $digits = preg_replace('/\D/', '', $ssn);
        return '***-**-' . substr($digits, -4);
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        return '***-***-' . substr($digits, -4);
    }

    private function maskLicense(string $license): string
    {
        return '****' . substr($license, -4);
    }

    private function maskAddress(string $address): string
    {
        $parts = explode(',', $address);
        $masked = array_map(fn ($p) => preg_replace('/[a-zA-Z0-9]/', '*', trim($p)), $parts);
        return implode(', ', $masked);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        return substr($local, 0, 1) . '***@' . $domain;
    }

    private function maskDefault(string $value): string
    {
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', $len - 4) . substr($value, -4);
    }
}
