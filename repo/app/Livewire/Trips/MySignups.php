<?php

namespace App\Livewire\Trips;

use App\Enums\WaitlistStatus;
use App\Models\TripSignup;
use App\Models\TripWaitlistEntry;
use App\Services\ApiClient;
use Illuminate\Support\Facades\Auth;
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

    public function cancelSignup(string $signupId): void
    {
        $signup = TripSignup::where('id', $signupId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $response = app(ApiClient::class)->post('/signups/' . $signup->id . '/cancel');

        if ($response->status() >= 400) {
            $this->addError('cancel', $response->json('message') ?? 'Failed to cancel signup.');
            return;
        }

        $this->dispatch('notify', type: 'success', message: 'Signup cancelled.');
    }

    public function acceptOffer(string $entryId): void
    {
        $entry = TripWaitlistEntry::where('id', $entryId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $response = app(ApiClient::class)->post('/waitlist/' . $entry->id . '/accept', [
            'idempotency_key' => 'waitlist-accept-' . $entry->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('offer', $response->json('message') ?? 'Failed to accept offer.');
            return;
        }

        $data   = $response->json();
        $signup = TripSignup::find($data['id']);
        $this->dispatch('notify', type: 'success', message: 'Seat offer accepted! Complete your booking.');
        $this->redirectRoute('trips.signup', ['trip' => $entry->trip, 'signup' => $signup]);
    }

    public function declineOffer(string $entryId): void
    {
        $entry = TripWaitlistEntry::where('id', $entryId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $response = app(ApiClient::class)->post('/waitlist/' . $entry->id . '/decline', [
            'idempotency_key' => 'waitlist.decline.' . $entry->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('offer', $response->json('message') ?? 'Failed to decline offer.');
            return;
        }

        $this->dispatch('notify', type: 'info', message: 'Offer declined.');
    }
}
