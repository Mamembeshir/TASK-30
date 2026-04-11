<?php

namespace App\Livewire\Trips;

use App\Enums\SignupStatus;
use App\Enums\WaitlistStatus;
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
class MySignups extends Component
{
    public function render()
    {
        $userId = Auth::id();

        $signups = TripSignup::with('trip')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        $waitlistEntries = TripWaitlistEntry::with('trip')
            ->where('user_id', $userId)
            ->whereIn('status', [WaitlistStatus::WAITING->value, WaitlistStatus::OFFERED->value])
            ->orderBy('created_at')
            ->get();

        return view('livewire.trips.my-signups', compact('signups', 'waitlistEntries'));
    }

    #[On('echo-private:user.{authUserId},WaitlistOfferMade')]
    public function onWaitlistOfferMade(array $data): void
    {
        $this->dispatch('notify', type: 'warning', message: "A seat opened up for \"{$data['tripTitle']}\"! Accept before the offer expires.");
        // Re-render to show updated waitlist state
    }

    public function getAuthUserIdProperty(): string
    {
        return Auth::id();
    }

    public function cancelSignup(string $signupId, SeatService $seatService): void
    {
        $signup = TripSignup::where('id', $signupId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        try {
            if ($signup->status === SignupStatus::CONFIRMED) {
                $seatService->cancelConfirmedSignup($signup);
            } elseif ($signup->status === SignupStatus::HOLD) {
                $seatService->releaseSeat($signup, \App\Enums\HoldReleaseReason::CANCELLED);
            }
            $this->dispatch('notify', type: 'success', message: 'Signup cancelled.');
        } catch (\RuntimeException $e) {
            $this->addError('cancel', $e->getMessage());
        }
    }

    public function acceptOffer(string $entryId, WaitlistService $waitlistService): void
    {
        $entry = TripWaitlistEntry::where('id', $entryId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        try {
            $signup = $waitlistService->acceptOffer($entry, Str::uuid()->toString());
            $this->dispatch('notify', type: 'success', message: 'Seat offer accepted! Complete your booking.');
            $this->redirectRoute('trips.signup', ['trip' => $entry->trip, 'signup' => $signup]);
        } catch (\RuntimeException $e) {
            $this->addError('offer', $e->getMessage());
        }
    }

    public function declineOffer(string $entryId, WaitlistService $waitlistService): void
    {
        $entry = TripWaitlistEntry::where('id', $entryId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        try {
            $waitlistService->declineOffer($entry, 'waitlist.decline.' . $entry->id);
            $this->dispatch('notify', type: 'info', message: 'Offer declined.');
        } catch (\RuntimeException $e) {
            $this->addError('offer', $e->getMessage());
        }
    }
}
