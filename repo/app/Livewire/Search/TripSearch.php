<?php

namespace App\Livewire\Search;

use App\Enums\TripDifficulty;
use App\Services\SearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TripSearch extends Component
{
    use WithPagination;

    // ── Search & filter state ──────────────────────────────────────────────────
    #[Url]
    public string $query = '';

    #[Url]
    public string $filterSpecialty = '';

    #[Url]
    public string $filterDateFrom = '';

    #[Url]
    public string $filterDateTo = '';

    /** @var array<string> TripDifficulty values */
    #[Url]
    public array $filterDifficulties = [];

    #[Url]
    public string $filterDurationMin = '';

    #[Url]
    public string $filterDurationMax = '';

    #[Url]
    public bool $filterPrerequisites = false;

    #[Url]
    public string $sort = 'newest';

    // ── UI state ──────────────────────────────────────────────────────────────
    public bool $showHistory = false;

    /** @var array<int, array{term: string, category: string|null}> */
    public array $typeAheadResults = [];

    // ── Hooks ──────────────────────────────────────────────────────────────────

    public function updatedQuery(): void
    {
        $this->resetPage();
        $this->updateTypeAhead();
    }

    public function updatedFilterSpecialty(): void { $this->resetPage(); }
    public function updatedFilterDateFrom(): void  { $this->resetPage(); }
    public function updatedFilterDateTo(): void    { $this->resetPage(); }
    public function updatedFilterDifficulties(): void { $this->resetPage(); }
    public function updatedFilterDurationMin(): void  { $this->resetPage(); }
    public function updatedFilterDurationMax(): void  { $this->resetPage(); }
    public function updatedFilterPrerequisites(): void { $this->resetPage(); }
    public function updatedSort(): void               { $this->resetPage(); }

    // ── Actions ────────────────────────────────────────────────────────────────

    /**
     * Called by Alpine.js debounce when query changes (type-ahead).
     * Also invoked on keyboard navigation.
     */
    public function updateTypeAhead(): void
    {
        $this->typeAheadResults = app(SearchService::class)->typeAhead($this->query);
    }

    public function selectSuggestion(string $term): void
    {
        $this->query           = $term;
        $this->typeAheadResults = [];
        $this->resetPage();
    }

    public function clearTypeAhead(): void
    {
        $this->typeAheadResults = [];
    }

    public function clearHistory(): void
    {
        $user = auth()->user();
        if ($user) {
            app(SearchService::class)->clearHistory($user);
            $this->dispatch('notify', type: 'success', message: 'Search history cleared.');
        }
    }

    public function resetFilters(): void
    {
        $this->filterSpecialty    = '';
        $this->filterDateFrom     = '';
        $this->filterDateTo       = '';
        $this->filterDifficulties = [];
        $this->filterDurationMin  = '';
        $this->filterDurationMax  = '';
        $this->filterPrerequisites = false;
        $this->resetPage();
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    public function render(SearchService $searchService): \Illuminate\View\View
    {
        $user    = auth()->user();
        $filters = $this->buildFilters();

        $trips   = $searchService->search($this->query, $filters, $this->sort, $user);
        $history = $user ? $searchService->getUserHistory($user) : collect();

        return view('livewire.search.trip-search', [
            'trips'       => $trips,
            'history'     => $history,
            'difficulties'=> TripDifficulty::cases(),
            'sortOptions' => $this->sortOptions(),
        ])->layout('layouts.app', ['title' => 'Search Trips']);
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function buildFilters(): array
    {
        $f = [];

        if ($this->filterSpecialty !== '')     $f['specialty']       = $this->filterSpecialty;
        if ($this->filterDateFrom !== '')      $f['date_from']       = $this->filterDateFrom;
        if ($this->filterDateTo !== '')        $f['date_to']         = $this->filterDateTo;
        if (! empty($this->filterDifficulties)) $f['difficulties']  = $this->filterDifficulties;
        if ($this->filterDurationMin !== '')   $f['duration_min']    = $this->filterDurationMin;
        if ($this->filterDurationMax !== '')   $f['duration_max']    = $this->filterDurationMax;
        if ($this->filterPrerequisites)        $f['has_prerequisites'] = true;

        return $f;
    }

    private function sortOptions(): array
    {
        return [
            'newest'        => 'Newest',
            'most_booked'   => 'Most Booked',
            'highest_rated' => 'Highest Rated',
            'price_asc'     => 'Price: Low to High',
            'price_desc'    => 'Price: High to Low',
        ];
    }
}
