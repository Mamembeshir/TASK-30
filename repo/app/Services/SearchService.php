<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Models\SearchTerm;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserSearchHistory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchService
{
    // ── SRCH-01/02/03: Full search ─────────────────────────────────────────────

    /**
     * Full-text search over trips (title, description, specialty, destination, doctor name).
     * Applies filters (SRCH-02) and sort (SRCH-03).
     * Records history and increments SearchTerm usage_count.
     *
     * $filters keys:
     *   specialty        string|null
     *   date_from        string|null  (Y-m-d)
     *   date_to          string|null  (Y-m-d)
     *   difficulties     array        TripDifficulty values
     *   duration_min     int|null     days
     *   duration_max     int|null     days
     *   has_prerequisites bool|null
     *
     * $sort values: most_booked | newest | highest_rated | price_asc | price_desc
     */
    public function search(
        string $query,
        array  $filters,
        string $sort,
        ?User  $user,
        int    $perPage = 12
    ): LengthAwarePaginator {
        $q = Trip::query()
            ->leftJoin('doctors', 'trips.lead_doctor_id', '=', 'doctors.id')
            ->leftJoin('users as doctor_users', 'doctors.user_id', '=', 'doctor_users.id')
            ->select('trips.*')
            ->whereIn('trips.status', [
                TripStatus::PUBLISHED->value,
                TripStatus::FULL->value,
            ]);

        // ── Full-text keyword search ──────────────────────────────────────────
        if ($query !== '') {
            $like = "%{$query}%";
            $q->where(function ($inner) use ($query, $like) {
                $inner->whereRaw(
                    "to_tsvector('english',
                        coalesce(trips.title,'') || ' ' ||
                        coalesce(trips.description,'') || ' ' ||
                        coalesce(trips.specialty,'') || ' ' ||
                        coalesce(trips.destination,'')
                    ) @@ plainto_tsquery('english', ?)",
                    [$query]
                )
                ->orWhere('trips.title',       'ilike', $like)
                ->orWhere('trips.destination', 'ilike', $like)
                ->orWhere('trips.specialty',   'ilike', $like)
                ->orWhere('doctor_users.username', 'ilike', $like);
            });
        }

        // ── SRCH-02 Filters ──────────────────────────────────────────────────
        if (! empty($filters['specialty'])) {
            $q->where('trips.specialty', 'ilike', "%{$filters['specialty']}%");
        }

        if (! empty($filters['date_from'])) {
            $q->whereDate('trips.start_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $q->whereDate('trips.end_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['difficulties'])) {
            $q->whereIn('trips.difficulty_level', (array) $filters['difficulties']);
        }

        if (isset($filters['duration_min']) && $filters['duration_min'] !== '') {
            $q->whereRaw('(trips.end_date - trips.start_date) >= ?', [(int) $filters['duration_min']]);
        }

        if (isset($filters['duration_max']) && $filters['duration_max'] !== '') {
            $q->whereRaw('(trips.end_date - trips.start_date) <= ?', [(int) $filters['duration_max']]);
        }

        if (! empty($filters['has_prerequisites'])) {
            $q->whereNotNull('trips.prerequisites')
              ->where('trips.prerequisites', '!=', '');
        }

        // ── SRCH-03 Sort ─────────────────────────────────────────────────────
        match ($sort) {
            'most_booked'    => $q->orderByDesc('trips.booking_count'),
            'highest_rated'  => $q->orderByRaw('trips.average_rating DESC NULLS LAST'),
            'price_asc'      => $q->orderBy('trips.price_cents'),
            'price_desc'     => $q->orderByDesc('trips.price_cents'),
            default          => $q->orderByDesc('trips.created_at'), // newest
        };

        $results = $q->with(['doctor.user'])->paginate($perPage);

        // ── Record search history + increment terms ───────────────────────────
        if ($user && $query !== '') {
            $this->recordSearch($user, $query, $filters, $results->total());
            $this->incrementSearchTerms($query);
        }

        return $results;
    }

    // ── SRCH-04: Type-ahead ────────────────────────────────────────────────────

    /**
     * Return up to 5 SearchTerm suggestions for the given prefix.
     * Requires at least 2 characters; returns empty array otherwise.
     *
     * @return array<int, array{term: string, category: string|null}>
     */
    public function typeAhead(string $prefix): array
    {
        if (mb_strlen($prefix) < 2) {
            return [];
        }

        return SearchTerm::where('term', 'ilike', mb_strtolower($prefix) . '%')
            ->orderByDesc('usage_count')
            ->limit(5)
            ->get(['term', 'category'])
            ->map(fn ($t) => ['term' => $t->term, 'category' => $t->category])
            ->toArray();
    }

    // ── SRCH-05: History ───────────────────────────────────────────────────────

    /**
     * Return the last 20 search history entries for the given user.
     */
    public function getUserHistory(User $user): Collection
    {
        return UserSearchHistory::where('user_id', $user->id)
            ->orderByDesc('searched_at')
            ->limit(20)
            ->get();
    }

    /**
     * Delete all search history for the given user.
     */
    public function clearHistory(User $user): void
    {
        UserSearchHistory::where('user_id', $user->id)->delete();
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function recordSearch(User $user, string $query, array $filters, int $resultCount): void
    {
        UserSearchHistory::create([
            'user_id'      => $user->id,
            'query'        => $query,
            'filters'      => empty($filters) ? null : $filters,
            'result_count' => $resultCount,
            'searched_at'  => now(),
        ]);

        // Enforce 20-entry cap: delete oldest beyond limit
        $ids = UserSearchHistory::where('user_id', $user->id)
            ->orderByDesc('searched_at')
            ->skip(20)
            ->limit(PHP_INT_MAX)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            UserSearchHistory::whereIn('id', $ids)->delete();
        }
    }

    private function incrementSearchTerms(string $query): void
    {
        // Increment each word-token that exists in search_terms
        $words = array_filter(explode(' ', mb_strtolower(trim($query))));
        foreach ($words as $word) {
            SearchTerm::where('term', $word)->increment('usage_count');
        }
        // Also try the full phrase
        SearchTerm::where('term', mb_strtolower(trim($query)))->increment('usage_count');
    }
}
