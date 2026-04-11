<?php

namespace App\Events;

use App\Models\Trip;
use App\Models\TripWaitlistEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaitlistOfferMade implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Trip               $trip,
        public readonly TripWaitlistEntry  $entry,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->entry->user_id}");
    }

    public function broadcastWith(): array
    {
        return [
            'tripId'         => $this->trip->id,
            'tripTitle'      => $this->trip->title,
            'entryId'        => $this->entry->id,
            'offerExpiresAt' => $this->entry->offer_expires_at?->toIso8601String(),
        ];
    }
}
