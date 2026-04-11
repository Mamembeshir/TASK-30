<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seat Hold Duration
    |--------------------------------------------------------------------------
    | How long (in minutes) a seat hold is valid before it expires.
    */
    'seat_hold_minutes' => (int) env('SEAT_HOLD_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Waitlist Offer Duration
    |--------------------------------------------------------------------------
    | How long (in minutes) a waitlist offer remains open before expiring.
    */
    'waitlist_offer_minutes' => (int) env('WAITLIST_OFFER_MINUTES', 10),

];
