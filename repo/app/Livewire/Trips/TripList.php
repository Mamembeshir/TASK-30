<?php

namespace App\Livewire\Trips;

use App\Enums\TripDifficulty;
use App\Enums\TripStatus;
use App\Models\Trip;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TripList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filterDifficulty = '';

    #[Url]
    public string $filterSpecialty = '';

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedFilterDifficulty(): void { $this->resetPage(); }
    public function updatedFilterSpecialty(): void  { $this->resetPage(); }

    public function render()
    {
        $query = Trip::with('doctor.user')
            ->whereIn('status', [TripStatus::PUBLISHED->value, TripStatus::FULL->value])
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->where('title', 'ilike', "%{$this->search}%")
                       ->orWhere('destination', 'ilike', "%{$this->search}%")
                       ->orWhere('specialty', 'ilike', "%{$this->search}%")
                )
            )
            ->when($this->filterDifficulty, fn ($q) =>
                $q->where('difficulty_level', $this->filterDifficulty)
            )
            ->when($this->filterSpecialty, fn ($q) =>
                $q->where('specialty', 'ilike', "%{$this->filterSpecialty}%")
            )
            ->orderBy('start_date');

        return view('livewire.trips.trip-list', [
            'trips'       => $query->paginate(12),
            'difficulties' => TripDifficulty::cases(),
        ]);
    }
}
