<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\MembershipOrder;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class SystemConfig extends Component
{
    public function render()
    {
        $config = [
            [
                'key'         => 'seat_hold_minutes',
                'label'       => 'Seat hold duration',
                'value'       => config('medvoyage.seat_hold_minutes') . ' minutes',
                'description' => 'How long a held seat remains reserved before expiring.',
            ],
            [
                'key'         => 'waitlist_offer_minutes',
                'label'       => 'Waitlist offer duration',
                'value'       => config('medvoyage.waitlist_offer_minutes') . ' minutes',
                'description' => 'How long a waitlist offer is open before it expires.',
            ],
            [
                'key'         => 'idempotency_ttl_hours',
                'label'       => 'Idempotency TTL',
                'value'       => config('medvoyage.idempotency_ttl_hours') . ' hours',
                'description' => 'How long idempotency records are retained.',
            ],
            [
                'key'         => 'session_lifetime',
                'label'       => 'Session lifetime',
                'value'       => config('session.lifetime') . ' minutes',
                'description' => 'How long an authenticated session persists.',
            ],
        ];

        $environment = [
            'app_env'        => app()->environment(),
            'app_debug'      => config('app.debug') ? 'enabled' : 'disabled',
            'app_url'        => config('app.url'),
            'php_version'    => PHP_VERSION,
            'laravel'        => app()->version(),
            'timezone'       => config('app.timezone'),
            'session_driver' => config('session.driver'),
            'queue_driver'   => config('queue.default'),
            'cache_driver'   => config('cache.default'),
            'db_connection'  => config('database.default'),
        ];

        $stats = [
            'users'         => User::count(),
            'doctors'       => Doctor::count(),
            'trips'         => Trip::count(),
            'payments'      => Payment::count(),
            'memberships'   => MembershipOrder::count(),
            'audit_entries' => AuditLog::count(),
        ];

        return view('livewire.admin.system-config', [
            'config'      => $config,
            'environment' => $environment,
            'stats'       => $stats,
        ])->layout('layouts.app', ['title' => 'System Configuration']);
    }
}
