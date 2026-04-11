<?php

namespace App\Livewire;

use App\Enums\CredentialingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TripStatus;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\MembershipOrder;
use App\Models\Payment;
use App\Models\Trip;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            [
                'label' => 'Active Trips',
                'value' => (string) Trip::where('status', TripStatus::PUBLISHED)->count(),
                'hint'  => 'Currently published',
            ],
            [
                'label' => 'Pending Credentialing',
                'value' => (string) Doctor::whereNotIn('credentialing_status', [
                                CredentialingStatus::APPROVED->value,
                                CredentialingStatus::REJECTED->value,
                            ])->count(),
                'hint'  => 'Awaiting review',
            ],
            [
                'label' => "Today's Payments",
                'value' => formatCurrency(
                    (int) Payment::whereIn('status', [PaymentStatus::RECORDED, PaymentStatus::CONFIRMED])
                        ->whereDate('created_at', today())
                        ->sum('amount_cents')
                ),
                'hint'  => Payment::whereDate('created_at', today())->count() . ' transactions',
            ],
            [
                'label' => 'Active Members',
                'value' => (string) MembershipOrder::where('status', OrderStatus::PAID)
                                ->where('expires_at', '>', now())
                                ->distinct('user_id')
                                ->count('user_id'),
                'hint'  => 'Non-expired memberships',
            ],
        ];

        $recentActivity = AuditLog::with('actor.profile')
            ->latest('created_at')
            ->limit(8)
            ->get();

        $upcomingTrips = Trip::where('status', TripStatus::PUBLISHED)
            ->where('start_date', '>=', today())
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        return view('livewire.dashboard', [
            'stats'          => $stats,
            'recentActivity' => $recentActivity,
            'upcomingTrips'  => $upcomingTrips,
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
