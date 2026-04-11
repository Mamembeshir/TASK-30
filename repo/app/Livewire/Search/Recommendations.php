<?php

namespace App\Livewire\Search;

use App\Services\RecommendationService;
use Livewire\Component;

class Recommendations extends Component
{
    public function render(RecommendationService $service): \Illuminate\View\View
    {
        $user = auth()->user();

        $sections = $user
            ? $service->getRecommendations($user, limit: 5)
            : [];

        return view('livewire.search.recommendations', [
            'sections' => $sections,
        ])->layout('layouts.app', ['title' => 'Recommendations']);
    }
}
