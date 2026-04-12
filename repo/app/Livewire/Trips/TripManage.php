<?php

namespace App\Livewire\Trips;

use App\Enums\TripDifficulty;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Trip;
use App\Services\ApiClient;
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

    public function save(): void
    {
        $this->validate([
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

        if ($this->trip?->exists) {
            // Update existing trip
            $response = app(ApiClient::class)->put('/admin/trips/' . $this->trip->id, [
                'title'            => $this->title,
                'description'      => $this->description,
                'lead_doctor_id'   => $this->leadDoctorId,
                'specialty'        => $this->specialty,
                'destination'      => $this->destination,
                'start_date'       => $this->startDate,
                'end_date'         => $this->endDate,
                'difficulty_level' => $this->difficultyLevel,
                'prerequisites'    => $this->prerequisites,
                'total_seats'      => $this->totalSeats,
                'price_cents'      => $this->priceCents,
            ]);

            if ($response->status() >= 400) {
                $this->addError('form', $response->json('message') ?? 'Failed to update trip.');
                return;
            }

            $data       = $response->json();
            $this->trip = Trip::find($data['id']) ?? $this->trip->fresh();
            $this->dispatch('notify', type: 'success', message: 'Trip updated.');
        } else {
            // Create new trip
            $this->createIdempotencyKey ??= (string) Str::uuid();

            $response = app(ApiClient::class)->post('/admin/trips', [
                'title'            => $this->title,
                'description'      => $this->description,
                'lead_doctor_id'   => $this->leadDoctorId,
                'specialty'        => $this->specialty,
                'destination'      => $this->destination,
                'start_date'       => $this->startDate,
                'end_date'         => $this->endDate,
                'difficulty_level' => $this->difficultyLevel,
                'prerequisites'    => $this->prerequisites,
                'total_seats'      => $this->totalSeats,
                'price_cents'      => $this->priceCents,
                'idempotency_key'  => $this->createIdempotencyKey,
            ]);

            if ($response->status() >= 400) {
                $this->addError('form', $response->json('message') ?? 'Failed to create trip.');
                return;
            }

            $data       = $response->json();
            $this->trip = Trip::find($data['id']);
            $this->dispatch('notify', type: 'success', message: 'Trip created.');
            $this->redirectRoute('admin.trips.manage', ['trip' => $this->trip]);
        }
    }

    public function publish(): void
    {
        $response = app(ApiClient::class)->post('/admin/trips/' . $this->trip->id . '/publish', [
            'idempotency_key' => 'trip.publish.' . $this->trip->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('status', $response->json('message') ?? 'Failed to publish trip.');
            return;
        }

        $data       = $response->json();
        $this->trip = Trip::find($data['id']) ?? $this->trip->fresh();
        $this->dispatch('notify', type: 'success', message: 'Trip published.');
    }

    public function close(): void
    {
        $response = app(ApiClient::class)->post('/admin/trips/' . $this->trip->id . '/close', [
            'idempotency_key' => 'trip.close.' . $this->trip->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('status', $response->json('message') ?? 'Failed to close trip.');
            return;
        }

        $data       = $response->json();
        $this->trip = Trip::find($data['id']) ?? $this->trip->fresh();
        $this->dispatch('notify', type: 'success', message: 'Trip closed for new signups.');
    }

    public function cancel(): void
    {
        $response = app(ApiClient::class)->post('/admin/trips/' . $this->trip->id . '/cancel', [
            'idempotency_key' => 'trip.cancel.' . $this->trip->id,
        ]);

        if ($response->status() >= 400) {
            $this->addError('status', $response->json('message') ?? 'Failed to cancel trip.');
            return;
        }

        $data       = $response->json();
        $this->trip = Trip::find($data['id']) ?? $this->trip->fresh();
        $this->dispatch('notify', type: 'success', message: 'Trip cancelled. All signups have been released.');
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
