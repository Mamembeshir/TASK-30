<?php

namespace App\Events;

use App\Models\TripSignup;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HoldExpiring implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TripSignup $signup,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->signup->user_id}");
    }

    public function broadcastWith(): array
    {
        return [
            'signupId'      => $this->signup->id,
            'tripId'        => $this->signup->trip_id,
            'holdExpiresAt' => $this->signup->hold_expires_at?->toIso8601String(),
        ];
    }
}
