<?php

namespace App\Http\Controllers\Api;

use App\Services\IdempotencyStore;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SearchApiController extends Controller
{
    /**
     * POST /api/search/history/clear
     *
     * Clear the authenticated user's search history.
     *
     * Body:
     *   idempotency_key  string  optional  (or Idempotency-Key header)
     *
     * 200 OK  – { "message": "Search history cleared." }
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $key = $request->input('idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'search.history.clear.' . $request->user()->id;

        $store = new IdempotencyStore();

        if ($store->alreadyProcessed($key, 'search.history.clear', $request->user()->id)) {
            return response()->json(['message' => 'Search history cleared.']);
        }

        app(SearchService::class)->clearHistory($request->user());

        $store->record($key, 'search.history.clear', 'User', $request->user()->id);

        return response()->json(['message' => 'Search history cleared.']);
    }
}
