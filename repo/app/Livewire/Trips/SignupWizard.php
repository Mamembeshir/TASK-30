<?php

namespace App\Livewire\Trips;

use App\Enums\SignupStatus;
use App\Enums\TenderType;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Services\PaymentService;
use App\Services\SeatService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 3-step wizard:
 *   Step 1 — Review trip details & hold summary
 *   Step 2 — Confirm personal/emergency info
 *   Step 3 — Payment (record tender; payment is confirmed separately by Finance)
 */
#[Layout('layouts.app')]
class SignupWizard extends Component
{
    public Trip       $trip;
    public TripSignup $signup;

    public int $step = 1;

    // Step 2 fields
    public string $emergencyContactName  = '';
    public string $emergencyContactPhone = '';
    public string $dietaryRequirements   = '';

    // Step 3 fields
    public string $tenderType     = 'CASH';
    public string $referenceNumber = '';
    public string $notes           = '';

    // Hold countdown (seconds remaining)
    public int $holdSecondsRemaining = 0;

    /**
     * Caller-stable idempotency key for the payment recorded in this wizard.
     *
     * Derived from the signup id so duplicate submits (double-clicks, retries
     * after a transient error, accidental form re-posts) collapse onto the
     * same Payment row via PaymentService::recordPayment's dedupe.
     */
    public string $paymentIdempotencyKey = '';

    public function mount(Trip $trip, TripSignup $signup): void
    {
        // Verify this signup belongs to the current user and is a HOLD
        if ($signup->user_id !== Auth::id() || $signup->status !== SignupStatus::HOLD) {
            abort(403);
        }

        // Enforce object-level consistency: the signup must belong to the route trip.
        // Without this check a user could load the wizard with their own HOLD signup
        // but a different trip URL, allowing the payment amount (derived below from the
        // signup's trip) to diverge from the seat actually being held.
        if ($signup->trip_id !== $trip->id) {
            abort(403);
        }

        $this->trip   = $trip;
        $this->signup = $signup;
        $this->holdSecondsRemaining = max(0, (int) now()->diffInSeconds($signup->hold_expires_at, false));
        $this->paymentIdempotencyKey = 'pay-signup-' . $signup->id;
    }

    #[On('echo-private:user.{signup.user_id},HoldExpiring')]
    public function onHoldExpiring(array $data): void
    {
        if ($data['signupId'] === $this->signup->id) {
            $this->dispatch('hold-expiring-warning');
        }
    }

    public function nextStep(): void
    {
        $this->validateStep();
        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function submitPayment(SeatService $seatService, PaymentService $paymentService): void
    {
        $this->validate([
            'tenderType' => 'required|in:CASH,CHECK,CARD_ON_FILE',
        ]);

        if ($this->signup->isHoldExpired()) {
            $this->addError('hold', 'Your hold has expired. Please restart the booking.');
            return;
        }

        try {
            // Record (not yet confirmed) payment via PaymentService — Finance
            // confirms it separately. Idempotent on $paymentIdempotencyKey, so
            // duplicate submissions return the existing Payment row.
            $payment = $paymentService->recordPayment(
                Auth::user(),
                TenderType::from($this->tenderType),
                (int) $this->signup->trip->price_cents,
                $this->referenceNumber !== '' ? $this->referenceNumber : null,
                $this->paymentIdempotencyKey,
            );

            $this->signup = $seatService->confirmSeat($this->signup, $payment->id);
            $this->step   = 4; // confirmation screen
        } catch (\RuntimeException $e) {
            $this->addError('payment', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.trips.signup-wizard');
    }

    private function validateStep(): void
    {
        if ($this->step === 2) {
            $this->validate([
                'emergencyContactName'  => 'required|string|max:200',
                'emergencyContactPhone' => 'required|string|max:30',
            ]);
        }
    }
}
