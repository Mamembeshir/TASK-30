<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class SystemConfig extends Component
{
    public function render()
    {
        return view('livewire.admin/system-config')
            ->layout('layouts.app', ['title' => 'System Configuration']);
    }
}
