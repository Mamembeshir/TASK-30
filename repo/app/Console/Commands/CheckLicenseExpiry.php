<?php

namespace App\Console\Commands;

use App\Enums\CredentialingStatus;
use App\Models\Doctor;
use App\Services\AuditService;
use Illuminate\Console\Command;

/**
 * CRED-08: Daily job — APPROVED doctors with license_expiry < today → EXPIRED.
 * Per questions.md 2.2: flags trips but does NOT auto-cancel them.
 */
class CheckLicenseExpiry extends Command
{
    protected $signature   = 'medvoyage:check-license-expiry';
    protected $description = 'Mark APPROVED doctors as EXPIRED when their license has lapsed (CRED-08)';

    public function handle(): int
    {
        $expiredDoctors = Doctor::where('credentialing_status', CredentialingStatus::APPROVED->value)
            ->whereNotNull('license_expiry')
            ->whereDate('license_expiry', '<', today())
            ->get();

        $count = 0;

        foreach ($expiredDoctors as $doctor) {
            try {
                $doctor->transitionCredentialingStatus(CredentialingStatus::EXPIRED);

                AuditService::record(
                    'doctor.license_expired',
                    'Doctor',
                    $doctor->id,
                    ['credentialing_status' => CredentialingStatus::APPROVED->value],
                    ['credentialing_status' => CredentialingStatus::EXPIRED->value],
                );

                $count++;
            } catch (\Throwable $e) {
                $this->error("Failed to expire doctor {$doctor->id}: {$e->getMessage()}");
            }
        }

        $this->info("Marked {$count} doctor(s) as EXPIRED.");

        return self::SUCCESS;
    }
}
