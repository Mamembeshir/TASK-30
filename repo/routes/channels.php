<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| trip.{tripId}   — public channel: seat/status updates visible to all viewers
| user.{userId}   — private channel: personal notifications (waitlist offer, hold expiry)
|
*/

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});
