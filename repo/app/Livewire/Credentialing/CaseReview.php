<?php

namespace App\Livewire\Credentialing;

use Livewire\Component;

class CaseReview extends Component
{
    public function render()
    {
        return view('livewire.credentialing/case-review')
            ->layout('layouts.app', ['title' => 'Case Review']);
    }
}
