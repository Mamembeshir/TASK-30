<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Trip $trip,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("trip.{$this->trip->id}");
    }

    public function broadcastWith(): array
    {
        return [
            'tripId'         => $this->trip->id,
            'status'         => $this->trip->status->value,
            'availableSeats' => $this->trip->available_seats,
        ];
    }
}
