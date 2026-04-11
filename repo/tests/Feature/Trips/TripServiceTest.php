<?php

use App\Enums\CredentialingStatus;
use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Enums\WaitlistStatus;
use App\Models\Doctor;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\TripWaitlistEntry;
use App\Models\User;
use App\Services\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ── create ────────────────────────────────────────────────────────────────────

it('creates a trip in DRAFT status', function () {
    $doctor  = Doctor::factory()->create(['credentialing_status' => CredentialingStatus::APPROVED->value]);
    $creator = User::factory()->create();

    $trip = app(TripService::class)->create([
        'title'            => 'Test Trip',
        'description'      => 'desc',
        'lead_doctor_id'   => $doctor->id,
        'specialty'        => 'Cardiology',
        'destination'      => 'Cairo, Egypt',
        'start_date'       => now()->addMonth(),
        'end_date'         => now()->addMonth()->addDays(10),
        'difficulty_level' => 'MODERATE',
        'total_seats'      => 10,
        'price_cents'      => 100000,
    ], $creator, (string) Str::uuid());

    expect($trip->status)->toBe(TripStatus::DRAFT)
        ->and($trip->available_seats)->toBe(10);
});

// ── create: idempotency (Audit Issue 3) ──────────────────────────────────────

it('TripService::create is idempotent on idempotency_key', function () {
    $doctor  = Doctor::factory()->create(['credentialing_status' => CredentialingStatus::APPROVED->value]);
    $creator = User::factory()->create();
    $key     = (string) Str::uuid();
    $svc     = app(TripService::class);

    $payload = [
        'title'            => 'Idempotent Trip',
        'description'      => 'desc',
        'lead_doctor_id'   => $doctor->id,
        'specialty'        => 'Cardiology',
        'destination'      => 'Cairo, Egypt',
        'start_date'       => now()->addMonth(),
        'end_date'         => now()->addMonth()->addDays(10),
        'difficulty_level' => 'MODERATE',
        'total_seats'      => 10,
        'price_cents'      => 100000,
    ];

    $first  = $svc->create($payload, $creator, $key);
    $second = $svc->create(array_merge($payload, ['title' => 'Changed Title']), $creator, $key);

    expect($first->id)->toBe($second->id)
        ->and($second->title)->toBe('Idempotent Trip') // original preserved
        ->and(Trip::count())->toBe(1);
});

// ── publish ───────────────────────────────────────────────────────────────────

it('publishes a DRAFT trip with an approved doctor', function () {
    $doctor = Doctor::factory()->create(['credentialing_status' => CredentialingStatus::APPROVED->value]);
    $trip   = Trip::factory()->create(['lead_doctor_id' => $doctor->id, 'status' => TripStatus::DRAFT->value]);

    app(TripService::class)->publish($trip);

    expect($trip->fresh()->status)->toBe(TripStatus::PUBLISHED);
});

it('rejects publishing with a non-approved doctor', function () {
    $doctor = Doctor::factory()->create(['credentialing_status' => CredentialingStatus::UNDER_REVIEW->value]);
    $trip   = Trip::factory()->create(['lead_doctor_id' => $doctor->id, 'status' => TripStatus::DRAFT->value]);

    expect(fn () => app(TripService::class)->publish($trip))
        ->toThrow(RuntimeException::class, 'not credentialed');
});

// ── cancel cascade ────────────────────────────────────────────────────────────

it('cancels a trip and releases all HOLD signups', function () {
    $trip    = Trip::factory()->published()->withSeats(5, 3)->create();
    $actor   = User::factory()->create();
    $signup  = TripSignup::factory()->for($trip)->for(User::factory()->create())->create([
        'status'          => SignupStatus::HOLD->value,
        'hold_expires_at' => now()->addMinutes(5),
    ]);

    app(TripService::class)->cancel($trip, $actor);

    expect($trip->fresh()->status)->toBe(TripStatus::CANCELLED)
        ->and($signup->fresh()->status)->toBe(SignupStatus::CANCELLED);
});

it('cancels a trip and declines waitlist entries', function () {
    $trip  = Trip::factory()->full()->withSeats(1, 0)->create();
    $actor = User::factory()->create();
    $entry = TripWaitlistEntry::factory()->for($trip)->for(User::factory()->create())->create([
        'status'   => WaitlistStatus::WAITING->value,
        'position' => 1,
    ]);

    app(TripService::class)->cancel($trip, $actor);

    expect($entry->fresh()->status)->toBe(WaitlistStatus::DECLINED);
});
