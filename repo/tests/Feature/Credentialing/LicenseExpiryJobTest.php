<?php

use App\Enums\CredentialingStatus;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('transitions APPROVED doctors with past license_expiry to EXPIRED', function () {
    $expiredDoctor = Doctor::factory()->approved()->withExpiredLicense()->create();
    $validDoctor   = Doctor::factory()->approved()->create([
        'license_expiry' => now()->addYear()->format('Y-m-d'),
    ]);

    $this->artisan('medvoyage:check-license-expiry')->assertExitCode(0);

    expect($expiredDoctor->fresh()->credentialing_status)->toBe(CredentialingStatus::EXPIRED);
    expect($validDoctor->fresh()->credentialing_status)->toBe(CredentialingStatus::APPROVED);
});

it('does not touch doctors that are not APPROVED', function () {
    $rejectedExpired = Doctor::factory()->rejected()->withExpiredLicense()->create();
    $notSubmitted    = Doctor::factory()->withExpiredLicense()->create([
        'credentialing_status' => CredentialingStatus::NOT_SUBMITTED,
    ]);

    $this->artisan('medvoyage:check-license-expiry');

    expect($rejectedExpired->fresh()->credentialing_status)->toBe(CredentialingStatus::REJECTED);
    expect($notSubmitted->fresh()->credentialing_status)->toBe(CredentialingStatus::NOT_SUBMITTED);
});

it('does not expire APPROVED doctors with today as expiry date (past means strictly before today)', function () {
    $today = Doctor::factory()->approved()->create([
        'license_expiry' => today()->format('Y-m-d'),
    ]);

    $this->artisan('medvoyage:check-license-expiry');

    // today's date is NOT past, so should remain APPROVED
    expect($today->fresh()->credentialing_status)->toBe(CredentialingStatus::APPROVED);
});

it('runs successfully with no expired doctors (zero count)', function () {
    Doctor::factory()->approved()->create([
        'license_expiry' => now()->addYear()->format('Y-m-d'),
    ]);

    $this->artisan('medvoyage:check-license-expiry')
         ->assertExitCode(0)
         ->expectsOutputToContain('Marked 0 doctor(s) as EXPIRED');
});
