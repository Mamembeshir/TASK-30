<?php

use App\Enums\ExceptionStatus;
use App\Enums\SettlementStatus;
use App\Enums\UserRole;
use App\Models\Settlement;
use App\Models\SettlementException;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// All mutation requests must include a same-origin Origin header so that
// VerifyApiCsrfToken grants the JSON exemption (mirrors real browser behaviour).
beforeEach(function () {
    $this->withHeaders(['Origin' => config('app.url')]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function settlementFinanceUser(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Finance', 'last_name' => 'Staff']);
    $user->addRole(UserRole::FINANCE_SPECIALIST);
    return $user->fresh();
}

function settlementPlainMember(): User
{
    $user = User::factory()->create();
    UserProfile::create(['user_id' => $user->id, 'first_name' => 'Plain', 'last_name' => 'Member']);
    $user->addRole(UserRole::MEMBER);
    return $user->fresh();
}

// ── POST /api/settlements/close ───────────────────────────────────────────────

it('POST /api/settlements/close creates a settlement for a given date', function () {
    $finance = settlementFinanceUser();
    $date    = now()->subDay()->toDateString();

    $this->actingAs($finance)
        ->postJson('/api/settlements/close', [
            'date'            => $date,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertOk()
        ->assertJsonPath('settlement_date', $date);
});

it('POST /api/settlements/close is idempotent on same date and key', function () {
    $finance = settlementFinanceUser();
    $date    = now()->subDays(2)->toDateString();
    $key     = (string) Str::uuid();

    $r1 = $this->actingAs($finance)
        ->postJson('/api/settlements/close', [
            'date'            => $date,
            'idempotency_key' => $key,
        ])
        ->assertOk();

    $r2 = $this->actingAs($finance)
        ->postJson('/api/settlements/close', [
            'date'            => $date,
            'idempotency_key' => $key,
        ])
        ->assertOk();

    expect($r1->json('id'))->toBe($r2->json('id'));
});

it('POST /api/settlements/close returns 403 for plain member', function () {
    $member = settlementPlainMember();

    $this->actingAs($member)
        ->postJson('/api/settlements/close', [
            'date' => now()->subDay()->toDateString(),
        ])
        ->assertForbidden();
});

it('POST /api/settlements/close returns 422 on missing date', function () {
    $finance = settlementFinanceUser();

    $this->actingAs($finance)
        ->postJson('/api/settlements/close', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('date');
});

// ── POST /api/settlements/{settlement}/resolve-exception ──────────────────────

it('POST /api/settlements/{settlement}/resolve-exception resolves an OPEN exception', function () {
    $finance    = settlementFinanceUser();
    $settlement = Settlement::factory()->withException()->create();
    $exception  = SettlementException::factory()->create([
        'settlement_id' => $settlement->id,
        'status'        => ExceptionStatus::OPEN->value,
    ]);

    $this->actingAs($finance)
        ->postJson("/api/settlements/{$settlement->id}/resolve-exception", [
            'exception_id'    => $exception->id,
            'resolution_type' => ExceptionStatus::RESOLVED->value,
            'resolution_note' => 'Reviewed and confirmed correct.',
        ])
        ->assertOk()
        ->assertJsonPath('status', ExceptionStatus::RESOLVED->value);
});

it('POST /api/settlements/{settlement}/resolve-exception returns 404 when exception belongs to different settlement', function () {
    $finance          = settlementFinanceUser();
    $settlement       = Settlement::factory()->withException()->create();
    $otherSettlement  = Settlement::factory()->withException()->create();
    $exception        = SettlementException::factory()->create([
        'settlement_id' => $otherSettlement->id,
        'status'        => ExceptionStatus::OPEN->value,
    ]);

    $this->actingAs($finance)
        ->postJson("/api/settlements/{$settlement->id}/resolve-exception", [
            'exception_id'    => $exception->id,
            'resolution_type' => ExceptionStatus::RESOLVED->value,
            'resolution_note' => 'Reviewed and confirmed correct.',
        ])
        ->assertNotFound();
});

it('POST /api/settlements/{settlement}/resolve-exception returns 403 for plain member', function () {
    $member     = settlementPlainMember();
    $settlement = Settlement::factory()->withException()->create();
    $exception  = SettlementException::factory()->create([
        'settlement_id' => $settlement->id,
    ]);

    $this->actingAs($member)
        ->postJson("/api/settlements/{$settlement->id}/resolve-exception", [
            'exception_id'    => $exception->id,
            'resolution_type' => ExceptionStatus::RESOLVED->value,
            'resolution_note' => 'Some note here.',
        ])
        ->assertForbidden();
});

it('POST /api/settlements/{settlement}/resolve-exception returns 422 when note is too short', function () {
    $finance    = settlementFinanceUser();
    $settlement = Settlement::factory()->withException()->create();
    $exception  = SettlementException::factory()->create([
        'settlement_id' => $settlement->id,
        'status'        => ExceptionStatus::OPEN->value,
    ]);

    $this->actingAs($finance)
        ->postJson("/api/settlements/{$settlement->id}/resolve-exception", [
            'exception_id'    => $exception->id,
            'resolution_type' => ExceptionStatus::RESOLVED->value,
            'resolution_note' => 'No', // too short (min:5)
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('resolution_note');
});

// ── POST /api/settlements/{settlement}/re-reconcile ───────────────────────────

it('POST /api/settlements/{settlement}/re-reconcile returns RECONCILED when all exceptions resolved', function () {
    $finance    = settlementFinanceUser();
    $settlement = Settlement::factory()->withException()->create();
    // Mark all exceptions as resolved so re-reconcile can succeed
    $settlement->exceptions()->create([
        'exception_type' => \App\Enums\ExceptionType::VARIANCE->value,
        'description'    => 'Test exception',
        'amount_cents'   => 500,
        'status'         => ExceptionStatus::RESOLVED->value,
        'version'        => 1,
    ]);

    $this->actingAs($finance)
        ->postJson("/api/settlements/{$settlement->id}/re-reconcile")
        ->assertOk()
        ->assertJsonPath('status', SettlementStatus::RECONCILED->value);
});

it('POST /api/settlements/{settlement}/re-reconcile returns 403 for plain member', function () {
    $member     = settlementPlainMember();
    $settlement = Settlement::factory()->withException()->create();

    $this->actingAs($member)
        ->postJson("/api/settlements/{$settlement->id}/re-reconcile")
        ->assertForbidden();
});

// ── GET /api/settlements/{settlement}/statement ───────────────────────────────

it('GET /api/settlements/{settlement}/statement returns file download', function () {
    $finance    = settlementFinanceUser();
    $settlement = Settlement::factory()->reconciled()->create();

    $response = $this->actingAs($finance)
        ->getJson("/api/settlements/{$settlement->id}/statement")
        ->assertOk()
        ->assertHeader('Content-Disposition')
        ->assertHeader('Content-Type');

    // Content-Disposition must signal an attachment with a filename
    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('filename');
});
