<?php

namespace App\Events;

use App\Models\Trip;
use App\Models\TripSignup;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SignupConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Trip       $trip,
        public readonly TripSignup $signup,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("trip.{$this->trip->id}");
    }

    public function broadcastWith(): array
    {
        return [
            'tripId'       => $this->trip->id,
            'bookingCount' => $this->trip->booking_count,
            'signupId'     => $this->signup->id,
        ];
    }
}
