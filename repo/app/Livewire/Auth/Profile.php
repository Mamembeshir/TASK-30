<?php

namespace App\Livewire\Auth;

use Livewire\Component;

class Profile extends Component
{
    public function render()
    {
        return view('livewire.auth.profile')
            ->layout('layouts.app', ['title' => 'My Profile']);
    }
}
