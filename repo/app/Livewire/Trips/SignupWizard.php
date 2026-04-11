<?php

namespace App\Livewire\Trips;

use App\Enums\SignupStatus;
use App\Models\Trip;
use App\Models\TripSignup;
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

    public function mount(Trip $trip, TripSignup $signup): void
    {
        // Verify this signup belongs to the current user and is a HOLD
        if ($signup->user_id !== Auth::id() || $signup->status !== SignupStatus::HOLD) {
            abort(403);
        }

        $this->trip   = $trip;
        $this->signup = $signup;
        $this->holdSecondsRemaining = max(0, (int) now()->diffInSeconds($signup->hold_expires_at, false));
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

    public function submitPayment(SeatService $seatService): void
    {
        $this->validate([
            'tenderType' => 'required|in:CASH,CHECK,CARD_ON_FILE',
        ]);

        if ($this->signup->isHoldExpired()) {
            $this->addError('hold', 'Your hold has expired. Please restart the booking.');
            return;
        }

        // In a real system this would create a Payment record via PaymentService.
        // For now we use a placeholder payment ID.
        $paymentId = 'PAY-' . strtoupper(substr(md5($this->signup->id . now()->timestamp), 0, 8));

        try {
            $this->signup = $seatService->confirmSeat($this->signup, $paymentId);
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
