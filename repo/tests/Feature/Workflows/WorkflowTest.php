<?php

/**
 * Step 8: End-to-End Workflow Integration Tests
 *
 * These tests exercise multi-step business flows using real services and an
 * in-memory (RefreshDatabase) PostgreSQL schema. No mocks — real DB writes.
 */

use App\Console\Commands\ExpireSeatHolds;
use App\Enums\CaseStatus;
use App\Enums\CredentialingStatus;
use App\Enums\DocumentType;
use App\Enums\ExceptionStatus;
use App\Enums\HoldReleaseReason;
use App\Enums\MembershipTier;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\RefundType;
use App\Enums\SettlementStatus;
use App\Enums\SignupStatus;
use App\Enums\TenderType;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WaitlistStatus;
use App\Exceptions\StaleRecordException;
use App\Models\Doctor;
use App\Models\DoctorDocument;
use App\Models\MembershipOrder;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\TripWaitlistEntry;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\CredentialingService;
use App\Services\MembershipService;
use App\Services\PaymentService;
use App\Services\ReviewService;
use App\Services\SeatService;
use App\Services\SettlementService;
use App\Services\TripService;
use App\Services\WaitlistService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Helpers ────────────────────────────────────────────────────────────────────

function wfUser(array $roles = [UserRole::MEMBER], UserStatus $status = UserStatus::ACTIVE): User
{
    $user = User::factory()->create(['status' => $status->value]);
    UserProfile::create([
        'user_id'    => $user->id,
        'first_name' => 'Test',
        'last_name'  => 'User',
    ]);
    foreach ($roles as $role) {
        $user->addRole($role);
    }
    return $user->fresh();
}

function wfDoctor(?CredentialingStatus $credStatus = null): array
{
    $user   = wfUser([UserRole::DOCTOR, UserRole::MEMBER]);
    $doctor = Doctor::factory()->create([
        'user_id'              => $user->id,
        'credentialing_status' => ($credStatus ?? CredentialingStatus::NOT_SUBMITTED)->value,
    ]);
    return [$user, $doctor->fresh()];
}

function wfPublishedTrip(int $total = 10, int $available = 10): Trip
{
    $doctor = Doctor::factory()->create(['credentialing_status' => CredentialingStatus::APPROVED->value]);
    return Trip::factory()->published()->withSeats($total, $available)->create([
        'lead_doctor_id' => $doctor->id,
    ]);
}

function wfRecordAndConfirmPayment(User $user, int $amountCents = 10000): Payment
{
    $paymentService = app(PaymentService::class);
    $payment = $paymentService->recordPayment(
        $user,
        TenderType::CARD_ON_FILE,
        $amountCents,
        null,
        Str::uuid()->toString()
    );
    return $paymentService->confirmPayment($payment, Str::uuid()->toString());
}

// ── 1. Member lifecycle ────────────────────────────────────────────────────────

it('member lifecycle: register → search → hold → confirm → review', function () {
    // Register via Livewire
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('username', 'lifecycle_user')
        ->set('email', 'lifecycle@example.com')
        ->set('first_name', 'Life')
        ->set('last_name', 'Cycle')
        ->set('password', 'Secure!Pass1')
        ->set('password_confirmation', 'Secure!Pass1')
        ->call('register')
        ->assertRedirect(route('dashboard'));

    $user = User::where('username', 'lifecycle_user')->firstOrFail();
    expect($user->isMember())->toBeTrue();

    // Publish a trip and search for it
    $trip = wfPublishedTrip(5, 5);
    $trip->update(['title' => 'Cardiac Surgery in Cairo']);

    Livewire::actingAs($user)
        ->test(\App\Livewire\Search\TripSearch::class)
        ->set('query', 'Cairo')
        ->assertSee('Cairo');

    // Hold a seat
    $seatService = app(SeatService::class);
    $key         = Str::uuid()->toString();
    $signup      = $seatService->holdSeat($trip, $user, $key);

    expect($signup->status)->toBe(SignupStatus::HOLD);
    expect($trip->fresh()->available_seats)->toBe(4);

    // Confirm with payment
    $payment = wfRecordAndConfirmPayment($user, $trip->price_cents);
    $signup  = $seatService->confirmSeat($signup, $payment->id);

    expect($signup->status)->toBe(SignupStatus::CONFIRMED);

    // Review after trip ends (close the trip, set end_date in past)
    $trip->forceFill(['status' => TripStatus::CLOSED->value, 'end_date' => now()->subDay()])->save();

    $review = app(ReviewService::class)->create($trip->fresh(), $user, 4, 'Great trip!');

    expect($review->rating)->toBe(4)
        ->and($review->trip_id)->toBe($trip->id);
});

// ── 2. Doctor credentialing lifecycle ─────────────────────────────────────────

it('doctor lifecycle: create → upload docs → submit → assign → review → approve → can lead trip', function () {
    [$docUser, $doctor] = wfDoctor();
    $admin    = wfUser([UserRole::ADMIN]);
    $reviewer = wfUser([UserRole::CREDENTIALING_REVIEWER]);

    // Upload required documents
    DoctorDocument::factory()->create([
        'doctor_id'     => $doctor->id,
        'document_type' => DocumentType::LICENSE,
        'uploaded_by'   => $docUser->id,
    ]);
    DoctorDocument::factory()->create([
        'doctor_id'     => $doctor->id,
        'document_type' => DocumentType::BOARD_CERTIFICATION,
        'uploaded_by'   => $docUser->id,
    ]);

    $credService = app(CredentialingService::class);

    // Submit case
    $case = $credService->submitCase($doctor->fresh(), $docUser);
    expect($case->status)->toBe(CaseStatus::SUBMITTED);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::UNDER_REVIEW);

    // Assign reviewer
    $credService->assignReviewer($case, $reviewer, $admin);
    expect($case->fresh()->assigned_reviewer)->toBe($reviewer->id);

    // Start review
    $credService->startReview($case->fresh(), $reviewer);
    expect($case->fresh()->status)->toBe(CaseStatus::INITIAL_REVIEW);

    // Approve
    $credService->approve($case->fresh(), $reviewer);
    expect($case->fresh()->status)->toBe(CaseStatus::APPROVED);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::APPROVED);

    // Approved doctor can now be used as lead on a published trip
    $trip = Trip::factory()->published()->create(['lead_doctor_id' => $doctor->id]);
    expect($trip->doctor->isApproved())->toBeTrue();
});

// ── 3. Credentialing rejection flow ───────────────────────────────────────────

it('credentialing rejection: submit → reject → new case → approve', function () {
    [$docUser, $doctor] = wfDoctor();
    $reviewer = wfUser([UserRole::CREDENTIALING_REVIEWER]);

    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::LICENSE,              'uploaded_by' => $docUser->id]);
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::BOARD_CERTIFICATION, 'uploaded_by' => $docUser->id]);

    $credService = app(CredentialingService::class);

    $case = $credService->submitCase($doctor->fresh(), $docUser);
    $credService->assignReviewer($case, $reviewer, $reviewer);
    $credService->startReview($case->fresh(), $reviewer);
    $credService->reject($case->fresh(), $reviewer, 'Documents are not legible.');

    expect($case->fresh()->status)->toBe(CaseStatus::REJECTED);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::REJECTED);

    // Submit a new case after rejection
    $case2 = $credService->submitCase($doctor->fresh(), $docUser);
    $credService->assignReviewer($case2, $reviewer, $reviewer);
    $credService->startReview($case2->fresh(), $reviewer);
    $credService->approve($case2->fresh(), $reviewer);

    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::APPROVED);
});

// ── 4. Credentialing request-materials flow ───────────────────────────────────

it('credentialing: submit → request materials → resubmit → approve', function () {
    [$docUser, $doctor] = wfDoctor();
    $reviewer = wfUser([UserRole::CREDENTIALING_REVIEWER]);

    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::LICENSE,              'uploaded_by' => $docUser->id]);
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::BOARD_CERTIFICATION, 'uploaded_by' => $docUser->id]);

    $credService = app(CredentialingService::class);

    $case = $credService->submitCase($doctor->fresh(), $docUser);
    $credService->assignReviewer($case, $reviewer, $reviewer);
    $credService->startReview($case->fresh(), $reviewer);
    $credService->requestMaterials($case->fresh(), $reviewer, 'Please upload a clearer copy of your license.');

    expect($case->fresh()->status)->toBe(CaseStatus::MORE_MATERIALS_REQUESTED);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::MORE_MATERIALS_REQUESTED);

    // Doctor uploads additional document and resubmits
    DoctorDocument::factory()->create(['doctor_id' => $doctor->id, 'document_type' => DocumentType::LICENSE, 'uploaded_by' => $docUser->id]);
    $credService->receiveMaterials($case->fresh(), $docUser);
    expect($case->fresh()->status)->toBe(CaseStatus::RE_REVIEW);

    // Approve the re-reviewed case
    $credService->approve($case->fresh(), $reviewer);
    expect($doctor->fresh()->credentialing_status)->toBe(CredentialingStatus::APPROVED);
});

// ── 5. Seat hold expiry ───────────────────────────────────────────────────────

it('seat hold expiry: hold → time passes → job expires hold → seat released → waitlist offered', function () {
    $trip   = wfPublishedTrip(1, 1);
    $member = wfUser([UserRole::MEMBER]);
    $waiter = wfUser([UserRole::MEMBER]);

    // Waiter joins the waitlist while seat is still available
    $waitEntry = app(WaitlistService::class)->joinWaitlist($trip, $waiter);
    expect($waitEntry->status)->toBe(WaitlistStatus::WAITING);

    // Member holds the last seat
    $signup = app(SeatService::class)->holdSeat($trip, $member, Str::uuid()->toString());
    expect($signup->status)->toBe(SignupStatus::HOLD);
    expect($trip->fresh()->available_seats)->toBe(0);

    // Advance clock past hold expiry
    Carbon::setTestNow(now()->addMinutes(11));

    // Run the expire command
    $this->artisan('medvoyage:expire-seat-holds')->assertSuccessful();

    Carbon::setTestNow(null);

    expect($signup->fresh()->status)->toBe(SignupStatus::EXPIRED);
    expect($trip->fresh()->available_seats)->toBe(1);

    // Waitlist entry should now be OFFERED
    expect($waitEntry->fresh()->status)->toBe(WaitlistStatus::OFFERED);
});

// ── 6. Waitlist flow ──────────────────────────────────────────────────────────

it('waitlist flow: full trip → join waitlist → confirmed cancels → offered → hold → confirm', function () {
    $trip      = wfPublishedTrip(1, 1);
    $confirmed = wfUser([UserRole::MEMBER]);
    $waiter    = wfUser([UserRole::MEMBER]);

    $seatService = app(SeatService::class);

    // Confirmed user takes the only seat
    $existingSignup = $seatService->holdSeat($trip, $confirmed, Str::uuid()->toString());
    $payment        = wfRecordAndConfirmPayment($confirmed, $trip->price_cents);
    $existingSignup = $seatService->confirmSeat($existingSignup, $payment->id);
    expect($trip->fresh()->status)->toBe(TripStatus::FULL);

    // Waiter joins the waitlist
    $waitEntry = app(WaitlistService::class)->joinWaitlist($trip->fresh(), $waiter);
    expect($waitEntry->status)->toBe(WaitlistStatus::WAITING);

    // Confirmed member cancels → seat released → waitlist offered
    $seatService->cancelConfirmedSignup($existingSignup->fresh());
    expect($waitEntry->fresh()->status)->toBe(WaitlistStatus::OFFERED);
    expect($trip->fresh()->available_seats)->toBe(1);

    // Waiter holds and confirms the offered seat
    $newSignup = $seatService->holdSeat($trip->fresh(), $waiter, Str::uuid()->toString());
    expect($newSignup->status)->toBe(SignupStatus::HOLD);

    $payment2  = wfRecordAndConfirmPayment($waiter, $trip->price_cents);
    $newSignup = $seatService->confirmSeat($newSignup, $payment2->id);
    expect($newSignup->status)->toBe(SignupStatus::CONFIRMED);
});

// ── 7. Membership: purchase → top-up → refund → BASIC still active ────────────

it('membership: purchase BASIC → top-up to PREMIUM → refund top-up → BASIC still active', function () {
    $member        = wfUser([UserRole::MEMBER]);
    $finance       = wfUser([UserRole::FINANCE_SPECIALIST]);
    $memberService = app(MembershipService::class);

    $basicPlan   = MembershipPlan::factory()->basic()->create();
    $premiumPlan = MembershipPlan::factory()->premium()->create();

    // Purchase BASIC: order starts PENDING, becomes PAID when payment confirmed
    $basicOrder   = $memberService->purchase($member, $basicPlan, Str::uuid()->toString());
    $basicPayment = wfRecordAndConfirmPayment($member, $basicPlan->price_cents);
    // Link payment and mark PAID (mirrors what confirmPayment cascade does)
    $basicOrder->update(['payment_id' => $basicPayment->id, 'status' => OrderStatus::PAID->value]);

    expect($member->fresh()->activeMembership()->plan_id)->toBe($basicPlan->id);

    // Top-up to PREMIUM within 30 days (price diff only)
    $topUpOrder   = $memberService->topUp($member->fresh(), $premiumPlan, Str::uuid()->toString());
    expect($topUpOrder->order_type)->toBe(OrderType::TOP_UP);
    expect($topUpOrder->amount_cents)->toBe($premiumPlan->price_cents - $basicPlan->price_cents);

    $topUpPayment = wfRecordAndConfirmPayment($member, $topUpOrder->amount_cents);
    $topUpOrder->update(['payment_id' => $topUpPayment->id, 'status' => OrderStatus::PAID->value]);

    // Request full refund on top-up
    $refund = $memberService->requestRefund(
        $topUpOrder->fresh(),
        RefundType::FULL,
        'Changed my mind on upgrade.',
        null,
        Str::uuid()->toString()
    );

    // Approve → process
    $refund = $memberService->approveRefund($refund->fresh(), $finance);
    $refund = $memberService->processRefund($refund->fresh());

    expect($refund->status->value)->toBe('PROCESSED');
    expect($topUpOrder->fresh()->status)->toBe(OrderStatus::REFUNDED);

    // BASIC order still active — user did not lose their base membership
    expect($member->fresh()->activeMembership()->plan_id)->toBe($basicPlan->id);
});

// ── 8. Finance: record → confirm → void → new → confirm → close → reconciled ──

it('finance: record → confirm → void (cascade) → new → confirm → close settlement → reconciled', function () {
    $member     = wfUser([UserRole::MEMBER]);
    $payService = app(PaymentService::class);
    $settService = app(SettlementService::class);
    $date = now()->toDateString();

    // Record and confirm a payment, then void it
    $p1 = $payService->recordPayment($member, TenderType::CASH, 5000, null, Str::uuid()->toString());
    $p1 = $payService->confirmPayment($p1, Str::uuid()->toString());
    $p1 = $payService->voidPayment($p1->fresh());
    expect($p1->status)->toBe(PaymentStatus::VOIDED);

    // Record and confirm a new payment
    $p2 = $payService->recordPayment($member, TenderType::CARD_ON_FILE, 5000, null, Str::uuid()->toString());
    $p2 = $payService->confirmPayment($p2, Str::uuid()->toString());
    expect($p2->status)->toBe(PaymentStatus::CONFIRMED);

    // Pre-seed settlement with expected_amount matching net (5000) → variance = 0 → reconciled
    Settlement::firstOrCreate(
        ['settlement_date' => $date],
        ['status' => SettlementStatus::OPEN->value, 'total_payments_cents' => 0, 'total_refunds_cents' => 0, 'net_amount_cents' => 0, 'expected_amount_cents' => 5000, 'variance_cents' => 0, 'version' => 1]
    );

    $settlement = $settService->closeDailySettlement($date);

    expect($settlement->status)->toBe(SettlementStatus::RECONCILED);
    expect($settlement->total_payments_cents)->toBe(5000);
    expect($settlement->variance_cents)->toBe(0);
});

// ── 9. Settlement exception ───────────────────────────────────────────────────

it('settlement exception: variance → exception flagged → resolve → re-reconcile', function () {
    $financeUser = wfUser([UserRole::FINANCE_SPECIALIST]);
    $member      = wfUser([UserRole::MEMBER]);
    $payService  = app(PaymentService::class);
    $settService = app(SettlementService::class);
    $date        = now()->toDateString();

    // Confirm a payment of 10000
    $p = $payService->recordPayment($member, TenderType::CASH, 10000, null, Str::uuid()->toString());
    $p = $payService->confirmPayment($p, Str::uuid()->toString());

    // Settlement expects 5000 but net is 10000 → variance 5000 → EXCEPTION
    Settlement::firstOrCreate(
        ['settlement_date' => $date],
        ['status' => SettlementStatus::OPEN->value, 'total_payments_cents' => 0, 'total_refunds_cents' => 0, 'net_amount_cents' => 0, 'expected_amount_cents' => 5000, 'variance_cents' => 0, 'version' => 1]
    );

    $settlement = $settService->closeDailySettlement($date);

    expect($settlement->status)->toBe(SettlementStatus::EXCEPTION);
    expect($settlement->variance_cents)->toBe(5000);

    // Exception record must be created
    $exception = $settlement->exceptions()->where('status', ExceptionStatus::OPEN->value)->first();
    expect($exception)->not->toBeNull();

    // Resolve the exception
    $settService->resolveException(
        $exception,
        ExceptionStatus::RESOLVED,
        'Variance accounted for: extra batch confirmed correct.',
        $financeUser
    );

    expect($exception->fresh()->status)->toBe(ExceptionStatus::RESOLVED);

    // Re-reconcile after all exceptions are resolved
    $settlement = $settService->reReconcile($settlement->fresh());
    expect($settlement->status)->toBe(SettlementStatus::RECONCILED);
});

// ── 10. Permission boundaries ─────────────────────────────────────────────────

it('permission boundaries: member → credentialing cases 403, finance → admin users 403, member → admin config 403', function () {
    $member  = wfUser([UserRole::MEMBER]);
    $finance = wfUser([UserRole::FINANCE_SPECIALIST]);

    // Member hits credentialing case list → 403
    $this->actingAs($member)
        ->get(route('credentialing.cases'))
        ->assertForbidden();

    // Finance specialist hits admin user list → 403
    $this->actingAs($finance)
        ->get(route('admin.users'))
        ->assertForbidden();

    // Member hits admin config → 403
    $this->actingAs($member)
        ->get(route('admin.config'))
        ->assertForbidden();
});

// ── 11. Concurrent seats ──────────────────────────────────────────────────────

it('concurrent seats: second hold on last seat is rejected; user can join waitlist instead', function () {
    $trip  = wfPublishedTrip(1, 1);
    $user1 = wfUser([UserRole::MEMBER]);
    $user2 = wfUser([UserRole::MEMBER]);

    $seatService = app(SeatService::class);

    // First user gets the hold
    $signup = $seatService->holdSeat($trip, $user1, Str::uuid()->toString());
    expect($signup->status)->toBe(SignupStatus::HOLD);
    expect($trip->fresh()->available_seats)->toBe(0);

    // Second user's hold request fails — no seats available
    expect(fn () => $seatService->holdSeat($trip->fresh(), $user2, Str::uuid()->toString()))
        ->toThrow(RuntimeException::class);

    // Second user joins the waitlist instead
    $waitEntry = app(WaitlistService::class)->joinWaitlist($trip->fresh(), $user2);
    expect($waitEntry->status)->toBe(WaitlistStatus::WAITING);
});

// ── 12. Idempotency ───────────────────────────────────────────────────────────

it('idempotency: recording a payment twice with the same key returns the same record', function () {
    $member      = wfUser([UserRole::MEMBER]);
    $payService  = app(PaymentService::class);
    $key         = Str::uuid()->toString();

    $p1 = $payService->recordPayment($member, TenderType::CASH, 5000, null, $key);
    $p2 = $payService->recordPayment($member, TenderType::CASH, 5000, null, $key); // same key

    expect($p1->id)->toBe($p2->id);
    expect(Payment::where('idempotency_key', $key)->count())->toBe(1);
});

// ── 13. Optimistic locking ────────────────────────────────────────────────────

it('optimistic locking: saving with a stale version throws StaleRecordException', function () {
    $doctor = Doctor::factory()->create(['credentialing_status' => CredentialingStatus::APPROVED->value]);
    $trip   = Trip::factory()->published()->create(['lead_doctor_id' => $doctor->id, 'version' => 1]);

    // Load two copies of the same trip
    $copy1 = Trip::find($trip->id);
    $copy2 = Trip::find($trip->id);

    // copy1 saves successfully → version bumped to 2
    $copy1->title = 'Updated by copy1';
    $copy1->saveWithLock();
    expect($copy1->fresh()->version)->toBe(2);

    // copy2 still has version=1 → stale → should throw
    $copy2->title = 'Updated by copy2 (stale)';
    expect(fn () => $copy2->saveWithLock())
        ->toThrow(StaleRecordException::class);
});
