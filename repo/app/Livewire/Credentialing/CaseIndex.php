<?php

namespace App\Livewire\Credentialing;

use Livewire\Component;

class CaseIndex extends Component
{
    public function render()
    {
        return view('livewire.credentialing/case-index')
            ->layout('layouts.app', ['title' => 'Credentialing Cases']);
    }
}
