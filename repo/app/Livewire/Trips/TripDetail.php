<?php

namespace App\Livewire\Trips;

use App\Enums\SignupStatus;
use App\Enums\TripStatus;
use App\Enums\WaitlistStatus;
use App\Models\Trip;
use App\Models\TripSignup;
use App\Models\TripWaitlistEntry;
use App\Services\SeatService;
use App\Services\WaitlistService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class TripDetail extends Component
{
    public Trip $trip;

    // Current user's signup/waitlist state
    public ?TripSignup $mySignup = null;
    public ?TripWaitlistEntry $myWaitlistEntry = null;

    public function mount(Trip $trip): void
    {
        $this->trip = $trip;
        $this->loadUserState();
    }

    // ── Real-time Echo listeners ───────────────────────────────────────────────

    #[On('echo:trip.{trip.id},SeatHeld')]
    public function onSeatHeld(array $data): void
    {
        $this->trip->available_seats = $data['availableSeats'];
        $this->trip->status          = TripStatus::from($data['status']);
    }

    #[On('echo:trip.{trip.id},SeatReleased')]
    public function onSeatReleased(array $data): void
    {
        $this->trip->available_seats = $data['availableSeats'];
        $this->trip->status          = TripStatus::from($data['status']);
    }

    #[On('echo:trip.{trip.id},TripStatusChanged')]
    public function onTripStatusChanged(array $data): void
    {
        $this->trip->status          = TripStatus::from($data['status']);
        $this->trip->available_seats = $data['availableSeats'];
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function holdSeat(SeatService $seatService)
    {
        if (! Auth::check()) {
            return $this->redirectRoute('login');
        }

        try {
            $idempotencyKey = Str::uuid()->toString();
            $signup         = $seatService->holdSeat($this->trip, Auth::user(), $idempotencyKey);
            $this->mySignup = $signup;
            $this->trip     = $this->trip->fresh();

            $this->dispatch('notify', type: 'success', message: 'Seat held for 10 minutes. Complete your booking before the timer runs out.');
            $this->redirectRoute('trips.signup', ['trip' => $this->trip, 'signup' => $signup]);
        } catch (\RuntimeException $e) {
            $this->addError('hold', $e->getMessage());
        }
    }

    public function joinWaitlist(WaitlistService $waitlistService)
    {
        if (! Auth::check()) {
            return $this->redirectRoute('login');
        }

        try {
            $entry                 = $waitlistService->joinWaitlist($this->trip, Auth::user());
            $this->myWaitlistEntry = $entry;
            $this->dispatch('notify', type: 'success', message: "You've been added to the waitlist at position {$entry->position}.");
        } catch (\RuntimeException $e) {
            $this->addError('waitlist', $e->getMessage());
        }
    }

    public function acceptOffer(WaitlistService $waitlistService): void
    {
        if (! $this->myWaitlistEntry || $this->myWaitlistEntry->status !== WaitlistStatus::OFFERED) {
            return;
        }

        try {
            $idempotencyKey = Str::uuid()->toString();
            $signup         = $waitlistService->acceptOffer($this->myWaitlistEntry, $idempotencyKey);
            $this->mySignup = $signup;
            $this->loadUserState();

            $this->dispatch('notify', type: 'success', message: 'Offer accepted! Complete your booking.');
            $this->redirectRoute('trips.signup', ['trip' => $this->trip, 'signup' => $signup]);
        } catch (\RuntimeException $e) {
            $this->addError('offer', $e->getMessage());
        }
    }

    public function declineOffer(WaitlistService $waitlistService): void
    {
        if (! $this->myWaitlistEntry) {
            return;
        }

        try {
            $waitlistService->declineOffer($this->myWaitlistEntry);
            $this->loadUserState();
            $this->dispatch('notify', type: 'info', message: 'Offer declined.');
        } catch (\RuntimeException $e) {
            $this->addError('offer', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.trips.trip-detail');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function loadUserState(): void
    {
        if (! Auth::check()) {
            return;
        }

        $userId = Auth::id();

        $this->mySignup = TripSignup::where('trip_id', $this->trip->id)
            ->where('user_id', $userId)
            ->whereIn('status', [SignupStatus::HOLD->value, SignupStatus::CONFIRMED->value])
            ->first();

        $this->myWaitlistEntry = TripWaitlistEntry::where('trip_id', $this->trip->id)
            ->where('user_id', $userId)
            ->whereIn('status', [WaitlistStatus::WAITING->value, WaitlistStatus::OFFERED->value])
            ->first();
    }
}
