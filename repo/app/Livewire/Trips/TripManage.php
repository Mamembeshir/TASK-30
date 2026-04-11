<?php

namespace App\Livewire\Trips;

use App\Enums\TripDifficulty;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin-only component: create / edit / publish / close / cancel trips.
 */
#[Layout('layouts.app')]
class TripManage extends Component
{
    public ?Trip $trip = null;

    // Form fields
    public string $title            = '';
    public string $description      = '';
    public string $leadDoctorId     = '';
    public string $specialty        = '';
    public string $destination      = '';
    public string $startDate        = '';
    public string $endDate          = '';
    public string $difficultyLevel  = TripDifficulty::MODERATE->value;
    public string $prerequisites    = '';
    public int    $totalSeats       = 10;
    public int    $priceCents       = 0;

    public bool $showForm = false;

    /**
     * Per-component idempotency key for trip creation. Initialized once per
     * component instance and reused across retries so that a double-click on
     * "Save" in the create form collapses onto a single `trips` row.
     */
    public ?string $createIdempotencyKey = null;

    public function mount(?Trip $trip = null): void
    {
        if (! Auth::check() || ! Auth::user()->hasRole(UserRole::ADMIN)) {
            abort(403, 'Access restricted to administrators.');
        }

        if ($trip?->exists) {
            $this->trip = $trip;
            $this->fill([
                'title'           => $trip->title,
                'description'     => $trip->description ?? '',
                'leadDoctorId'    => $trip->lead_doctor_id,
                'specialty'       => $trip->specialty,
                'destination'     => $trip->destination,
                'startDate'       => $trip->start_date->toDateString(),
                'endDate'         => $trip->end_date->toDateString(),
                'difficultyLevel' => $trip->difficulty_level->value,
                'prerequisites'   => $trip->prerequisites ?? '',
                'totalSeats'      => $trip->total_seats,
                'priceCents'      => $trip->price_cents,
            ]);
            $this->showForm = true;
        }
    }

    public function save(TripService $tripService): void
    {
        $data = $this->validate([
            'title'           => 'required|string|max:300',
            'description'     => 'nullable|string',
            'leadDoctorId'    => 'required|uuid|exists:doctors,id',
            'specialty'       => 'required|string|max:200',
            'destination'     => 'required|string|max:300',
            'startDate'       => 'required|date|after_or_equal:today',
            'endDate'         => 'required|date|after_or_equal:startDate',
            'difficultyLevel' => 'required|in:' . implode(',', array_column(TripDifficulty::cases(), 'value')),
            'prerequisites'   => 'nullable|string',
            'totalSeats'      => 'required|integer|min:1|max:500',
            'priceCents'      => 'required|integer|min:0',
        ]);

        $payload = [
            'title'           => $data['title'],
            'description'     => $data['description'],
            'lead_doctor_id'  => $data['leadDoctorId'],
            'specialty'       => $data['specialty'],
            'destination'     => $data['destination'],
            'start_date'      => $data['startDate'],
            'end_date'        => $data['endDate'],
            'difficulty_level' => $data['difficultyLevel'],
            'prerequisites'   => $data['prerequisites'],
            'total_seats'     => $data['totalSeats'],
            'price_cents'     => $data['priceCents'],
        ];

        try {
            if ($this->trip?->exists) {
                $this->trip = $tripService->update($this->trip, $payload);
                $this->dispatch('notify', type: 'success', message: 'Trip updated.');
            } else {
                $this->createIdempotencyKey ??= (string) Str::uuid();
                $this->trip = $tripService->create($payload, Auth::user(), $this->createIdempotencyKey);
                $this->dispatch('notify', type: 'success', message: 'Trip created.');
                $this->redirectRoute('admin.trips.manage', ['trip' => $this->trip]);
            }
        } catch (\RuntimeException $e) {
            $this->addError('form', $e->getMessage());
        }
    }

    public function publish(TripService $tripService): void
    {
        try {
            $this->trip = $tripService->publish($this->trip);
            $this->dispatch('notify', type: 'success', message: 'Trip published.');
        } catch (\RuntimeException $e) {
            $this->addError('status', $e->getMessage());
        }
    }

    public function close(TripService $tripService): void
    {
        try {
            $this->trip = $tripService->close($this->trip);
            $this->dispatch('notify', type: 'success', message: 'Trip closed for new signups.');
        } catch (\RuntimeException $e) {
            $this->addError('status', $e->getMessage());
        }
    }

    public function cancel(TripService $tripService): void
    {
        try {
            $this->trip = $tripService->cancel($this->trip, Auth::user());
            $this->dispatch('notify', type: 'success', message: 'Trip cancelled. All signups have been released.');
        } catch (\RuntimeException $e) {
            $this->addError('status', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.trips.trip-manage', [
            'doctors'      => Doctor::with('user')->where('credentialing_status', 'APPROVED')->get(),
            'difficulties' => TripDifficulty::cases(),
            'statuses'     => TripStatus::cases(),
        ]);
    }
}
