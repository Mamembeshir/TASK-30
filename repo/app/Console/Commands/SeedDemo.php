<?php

namespace App\Console\Commands;

use App\Enums\CaseStatus;
use App\Enums\CredentialingStatus;
use App\Enums\DocumentType;
use App\Enums\MembershipTier;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SettlementStatus;
use App\Enums\SignupStatus;
use App\Enums\TenderType;
use App\Enums\TripDifficulty;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WaitlistStatus;
use App\Models\CredentialingCase;
use App\Models\Doctor;
use App\Models\DoctorDocument;
use App\Models\MembershipOrder;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\SeatHold;
use App\Models\Trip;
use App\Models\TripReview;
use App\Models\TripSignup;
use App\Models\TripWaitlistEntry;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole as UserRoleModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedDemo extends Command
{
    protected $signature   = 'db:seed-demo';
    protected $description = 'Seed the database with realistic demo data for development and demonstration.';

    public function handle(): int
    {
        $this->info('Seeding demo data…');

        DB::statement('SET session_replication_role = replica'); // disable FK checks temporarily

        // Clear in dependency order
        DB::table('trip_reviews')->delete();
        DB::table('trip_waitlist_entries')->delete();
        DB::table('seat_holds')->delete();
        DB::table('trip_signups')->delete();
        DB::table('refunds')->delete();
        DB::table('payments')->delete();
        DB::table('membership_orders')->delete();
        DB::table('membership_plans')->delete();
        DB::table('trips')->delete();
        DB::table('credentialing_actions')->delete();
        DB::table('credentialing_cases')->delete();
        DB::table('doctor_documents')->delete();
        DB::table('doctors')->delete();
        DB::table('user_search_histories')->delete();
        DB::table('search_terms')->delete();
        DB::table('settlement_exceptions')->delete();
        DB::table('settlements')->delete();
        DB::table('audit_logs')->delete();
        DB::table('user_roles')->delete();
        DB::table('user_profiles')->delete();
        DB::table('users')->delete();

        DB::statement('SET session_replication_role = DEFAULT');

        // ── Users ─────────────────────────────────────────────────────────────

        $admin    = $this->makeUser('admin',    'admin@medvoyage.dev',    'Admin123!@',    UserStatus::ACTIVE, [UserRole::ADMIN],                    'System',    'Administrator');
        $drSmith  = $this->makeUser('drsmith',  'drsmith@medvoyage.dev',  'Doctor123!@',   UserStatus::ACTIVE, [UserRole::DOCTOR, UserRole::MEMBER],  'Alexander', 'Smith');
        $drJones  = $this->makeUser('drjones',  'drjones@medvoyage.dev',  'Doctor123!@',   UserStatus::ACTIVE, [UserRole::DOCTOR],                    'Patricia',  'Jones');
        $member1  = $this->makeUser('member1',  'member1@medvoyage.dev',  'Member123!@',   UserStatus::ACTIVE, [UserRole::MEMBER],                    'Jordan',    'Rivera');
        $member2  = $this->makeUser('member2',  'member2@medvoyage.dev',  'Member123!@',   UserStatus::ACTIVE, [UserRole::MEMBER],                    'Casey',     'Morgan');
        $reviewer = $this->makeUser('reviewer', 'reviewer@medvoyage.dev', 'Review123!@',   UserStatus::ACTIVE, [UserRole::CREDENTIALING_REVIEWER],    'Robin',     'Patel');
        $finance  = $this->makeUser('finance',  'finance@medvoyage.dev',  'Finance123!@',  UserStatus::ACTIVE, [UserRole::FINANCE_SPECIALIST],        'Sam',       'Lee');

        // ── Doctors ───────────────────────────────────────────────────────────

        $doctorSmith = Doctor::create([
            'user_id'                    => $drSmith->id,
            'specialty'                  => 'Cardiology',
            'npi_number'                 => '1234567890',
            'license_number_encrypted'   => encrypt('MD-12345'),
            'license_number_mask'        => '***45',
            'license_state'              => 'CA',
            'license_expiry'             => now()->addYears(2)->toDateString(),
            'credentialing_status'       => CredentialingStatus::APPROVED->value,
            'activated_at'               => now()->subMonths(3),
            'version'                    => 1,
        ]);

        $doctorJones = Doctor::create([
            'user_id'                    => $drJones->id,
            'specialty'                  => 'Orthopedics',
            'npi_number'                 => '0987654321',
            'license_number_encrypted'   => encrypt('MD-67890'),
            'license_number_mask'        => '***90',
            'license_state'              => 'NY',
            'license_expiry'             => now()->addYears(1)->toDateString(),
            'credentialing_status'       => CredentialingStatus::UNDER_REVIEW->value,
            'version'                    => 1,
        ]);

        // Documents for drSmith (approved)
        foreach ([DocumentType::LICENSE, DocumentType::BOARD_CERTIFICATION] as $type) {
            DoctorDocument::create([
                'doctor_id'     => $doctorSmith->id,
                'document_type' => $type->value,
                'file_path'     => "documents/{$doctorSmith->id}/{$type->value}.pdf",
                'file_name'     => strtolower($type->value) . '.pdf',
                'file_size'     => 102400,
                'mime_type'     => 'application/pdf',
                'checksum'      => bin2hex(random_bytes(16)),
                'uploaded_by'   => $drSmith->id,
            ]);
        }

        // Documents and pending case for drJones
        DoctorDocument::create([
            'doctor_id'     => $doctorJones->id,
            'document_type' => DocumentType::LICENSE->value,
            'file_path'     => "documents/{$doctorJones->id}/license.pdf",
            'file_name'     => 'license.pdf',
            'file_size'     => 81920,
            'mime_type'     => 'application/pdf',
            'checksum'      => bin2hex(random_bytes(16)),
            'uploaded_by'   => $drJones->id,
        ]);
        DoctorDocument::create([
            'doctor_id'     => $doctorJones->id,
            'document_type' => DocumentType::BOARD_CERTIFICATION->value,
            'file_path'     => "documents/{$doctorJones->id}/board_cert.pdf",
            'file_name'     => 'board_cert.pdf',
            'file_size'     => 73728,
            'mime_type'     => 'application/pdf',
            'checksum'      => bin2hex(random_bytes(16)),
            'uploaded_by'   => $drJones->id,
        ]);

        $jonesCase = CredentialingCase::create([
            'doctor_id'         => $doctorJones->id,
            'status'            => CaseStatus::INITIAL_REVIEW->value,
            'assigned_reviewer' => $reviewer->id,
            'submitted_at'      => now()->subWeek(),
            'version'           => 1,
        ]);

        // ── Membership plans ──────────────────────────────────────────────────

        $planBasic    = MembershipPlan::create(['name' => 'Basic',    'description' => 'Access to standard trips.',           'price_cents' => 9900,  'duration_months' => 12, 'tier' => MembershipTier::BASIC->value,    'is_active' => true, 'version' => 1]);
        $planStandard = MembershipPlan::create(['name' => 'Standard', 'description' => 'Access to all trips + priority.',     'price_cents' => 19900, 'duration_months' => 12, 'tier' => MembershipTier::STANDARD->value, 'is_active' => true, 'version' => 1]);
        $planPremium  = MembershipPlan::create(['name' => 'Premium',  'description' => 'Unlimited access + concierge service.', 'price_cents' => 39900, 'duration_months' => 12, 'tier' => MembershipTier::PREMIUM->value,  'is_active' => true, 'version' => 1]);

        // ── Trips ─────────────────────────────────────────────────────────────

        $tripPublishedA = Trip::create([
            'title'            => 'Cardiac Surgery in Nairobi',
            'description'      => 'A hands-on surgical trip to Nairobi\'s top cardiac center. Work alongside experienced surgeons.',
            'lead_doctor_id'   => $doctorSmith->id,
            'specialty'        => 'Cardiology',
            'destination'      => 'Nairobi, Kenya',
            'start_date'       => now()->addMonths(2)->toDateString(),
            'end_date'         => now()->addMonths(2)->addDays(10)->toDateString(),
            'difficulty_level' => TripDifficulty::MODERATE->value,
            'prerequisites'    => 'BLS certification required.',
            'total_seats'      => 10,
            'available_seats'  => 7,
            'price_cents'      => 150000,
            'status'           => TripStatus::PUBLISHED->value,
            'booking_count'    => 3,
            'average_rating'   => null,
            'created_by'       => $admin->id,
            'version'          => 1,
        ]);

        $tripPublishedFull = Trip::create([
            'title'            => 'Orthopedic Outreach in Lima',
            'description'      => 'Surgical outreach providing orthopedic care to underserved communities in Lima.',
            'lead_doctor_id'   => $doctorSmith->id,
            'specialty'        => 'Orthopedics',
            'destination'      => 'Lima, Peru',
            'start_date'       => now()->addMonths(3)->toDateString(),
            'end_date'         => now()->addMonths(3)->addDays(7)->toDateString(),
            'difficulty_level' => TripDifficulty::CHALLENGING->value,
            'prerequisites'    => null,
            'total_seats'      => 8,
            'available_seats'  => 0,
            'price_cents'      => 200000,
            'status'           => TripStatus::FULL->value,
            'booking_count'    => 8,
            'average_rating'   => null,
            'created_by'       => $admin->id,
            'version'          => 1,
        ]);

        $tripDraft = Trip::create([
            'title'            => 'Ophthalmology Mission — Cairo',
            'description'      => 'Cataract surgery outreach program in Cairo.',
            'lead_doctor_id'   => $doctorSmith->id,
            'specialty'        => 'Ophthalmology',
            'destination'      => 'Cairo, Egypt',
            'start_date'       => now()->addMonths(6)->toDateString(),
            'end_date'         => now()->addMonths(6)->addDays(14)->toDateString(),
            'difficulty_level' => TripDifficulty::EASY->value,
            'prerequisites'    => null,
            'total_seats'      => 12,
            'available_seats'  => 12,
            'price_cents'      => 120000,
            'status'           => TripStatus::DRAFT->value,
            'booking_count'    => 0,
            'average_rating'   => null,
            'created_by'       => $admin->id,
            'version'          => 1,
        ]);

        $tripClosed = Trip::create([
            'title'            => 'Cardiology Mission — Mumbai',
            'description'      => 'Completed mission providing cardiac care in Mumbai.',
            'lead_doctor_id'   => $doctorSmith->id,
            'specialty'        => 'Cardiology',
            'destination'      => 'Mumbai, India',
            'start_date'       => now()->subMonths(2)->toDateString(),
            'end_date'         => now()->subMonth()->toDateString(),
            'difficulty_level' => TripDifficulty::MODERATE->value,
            'prerequisites'    => null,
            'total_seats'      => 10,
            'available_seats'  => 0,
            'price_cents'      => 130000,
            'status'           => TripStatus::CLOSED->value,
            'booking_count'    => 10,
            'average_rating'   => 4.5,
            'created_by'       => $admin->id,
            'version'          => 1,
        ]);

        $tripCancelled = Trip::create([
            'title'            => 'Surgery Outreach — Bogotá (Cancelled)',
            'description'      => 'This trip was cancelled due to logistics.',
            'lead_doctor_id'   => $doctorSmith->id,
            'specialty'        => 'Surgery',
            'destination'      => 'Bogotá, Colombia',
            'start_date'       => now()->addMonth()->toDateString(),
            'end_date'         => now()->addMonth()->addDays(5)->toDateString(),
            'difficulty_level' => TripDifficulty::MODERATE->value,
            'prerequisites'    => null,
            'total_seats'      => 6,
            'available_seats'  => 6,
            'price_cents'      => 90000,
            'status'           => TripStatus::CANCELLED->value,
            'booking_count'    => 0,
            'average_rating'   => null,
            'created_by'       => $admin->id,
            'version'          => 1,
        ]);

        // ── Payments ──────────────────────────────────────────────────────────

        $payment1 = Payment::create([
            'user_id'           => $member1->id,
            'tender_type'       => TenderType::CARD_ON_FILE->value,
            'amount_cents'      => 19900,
            'status'            => PaymentStatus::CONFIRMED->value,
            'reference_number'  => 'REF-001',
            'confirmation_event_id' => Str::uuid(),
            'confirmed_at'      => now()->subDays(10),
            'idempotency_key'   => Str::uuid()->toString(),
            'version'           => 1,
        ]);

        $payment2 = Payment::create([
            'user_id'           => $member2->id,
            'tender_type'       => TenderType::CASH->value,
            'amount_cents'      => 150000,
            'status'            => PaymentStatus::CONFIRMED->value,
            'reference_number'  => null,
            'confirmation_event_id' => Str::uuid(),
            'confirmed_at'      => now()->subDays(5),
            'idempotency_key'   => Str::uuid()->toString(),
            'version'           => 1,
        ]);

        $payment3 = Payment::create([
            'user_id'           => $member1->id,
            'tender_type'       => TenderType::CHECK->value,
            'amount_cents'      => 150000,
            'status'            => PaymentStatus::RECORDED->value,
            'reference_number'  => 'CHK-456',
            'idempotency_key'   => Str::uuid()->toString(),
            'version'           => 1,
        ]);

        // ── Membership order for member1 ───────────────────────────────────────

        MembershipOrder::create([
            'user_id'               => $member1->id,
            'plan_id'               => $planStandard->id,
            'order_type'            => OrderType::PURCHASE->value,
            'amount_cents'          => $planStandard->price_cents,
            'status'                => OrderStatus::PAID->value,
            'starts_at'             => now()->subDays(10),
            'expires_at'            => now()->subDays(10)->addMonths(12),
            'top_up_eligible_until' => now()->subDays(10)->addDays(30),
            'payment_id'            => $payment1->id,
            'idempotency_key'       => Str::uuid()->toString(),
            'version'               => 1,
        ]);

        // ── Trip signups ──────────────────────────────────────────────────────

        // Confirmed signup on full trip (Lima) — member2
        TripSignup::create([
            'trip_id'          => $tripPublishedFull->id,
            'user_id'          => $member2->id,
            'status'           => SignupStatus::CONFIRMED->value,
            'confirmed_at'     => now()->subDays(5),
            'payment_id'       => $payment2->id,
            'idempotency_key'  => Str::uuid()->toString(),
            'version'          => 1,
        ]);

        // Confirmed signup on Nairobi trip — member1
        $signupNairobiMember1 = TripSignup::create([
            'trip_id'          => $tripPublishedA->id,
            'user_id'          => $member1->id,
            'status'           => SignupStatus::CONFIRMED->value,
            'confirmed_at'     => now()->subDays(3),
            'payment_id'       => $payment3->id,
            'idempotency_key'  => Str::uuid()->toString(),
            'version'          => 1,
        ]);

        // On-hold signup on Nairobi trip — member2
        $holdSignup = TripSignup::create([
            'trip_id'          => $tripPublishedA->id,
            'user_id'          => $member2->id,
            'status'           => SignupStatus::HOLD->value,
            'hold_expires_at'  => now()->addMinutes(8),
            'idempotency_key'  => Str::uuid()->toString(),
            'version'          => 1,
        ]);
        SeatHold::create([
            'trip_id'    => $tripPublishedA->id,
            'signup_id'  => $holdSignup->id,
            'held_at'    => now()->subMinutes(2),
            'expires_at' => now()->addMinutes(8),
        ]);

        // Expired signup
        TripSignup::create([
            'trip_id'          => $tripPublishedA->id,
            'user_id'          => $finance->id,
            'status'           => SignupStatus::EXPIRED->value,
            'hold_expires_at'  => now()->subMinutes(15),
            'idempotency_key'  => Str::uuid()->toString(),
            'version'          => 1,
        ]);

        // ── Waitlist entries on Lima (full) trip ──────────────────────────────

        TripWaitlistEntry::create([
            'trip_id'  => $tripPublishedFull->id,
            'user_id'  => $member1->id,
            'position' => 1,
            'status'   => WaitlistStatus::WAITING->value,
        ]);
        TripWaitlistEntry::create([
            'trip_id'  => $tripPublishedFull->id,
            'user_id'  => $finance->id,
            'position' => 2,
            'status'   => WaitlistStatus::WAITING->value,
        ]);

        // ── Reviews on closed Mumbai trip ─────────────────────────────────────

        TripReview::create([
            'trip_id'    => $tripClosed->id,
            'user_id'    => $member1->id,
            'rating'     => 5,
            'review_text' => 'An incredible experience. Highly professional team and life-changing work.',
            'status'     => \App\Enums\ReviewStatus::ACTIVE->value,
        ]);
        TripReview::create([
            'trip_id'    => $tripClosed->id,
            'user_id'    => $member2->id,
            'rating'     => 4,
            'review_text' => 'Very well organized. The local hospital staff were welcoming and supportive.',
            'status'     => \App\Enums\ReviewStatus::ACTIVE->value,
        ]);

        // ── Settlements ───────────────────────────────────────────────────────

        $yesterdayDate = now()->subDay()->toDateString();
        $s1 = Settlement::create([
            'settlement_date'       => $yesterdayDate,
            'status'                => SettlementStatus::RECONCILED->value,
            'total_payments_cents'  => 169900,
            'total_refunds_cents'   => 0,
            'net_amount_cents'      => 169900,
            'expected_amount_cents' => 169900,
            'variance_cents'        => 0,
            'closed_at'             => now()->subDay()->setTime(23, 59, 0),
            'reconciled_at'         => now()->subDay()->setTime(23, 59, 30),
            'version'               => 1,
        ]);
        Payment::whereIn('id', [$payment1->id, $payment2->id])->update(['settlement_id' => $s1->id]);

        // Today's open settlement
        Settlement::create([
            'settlement_date'       => now()->toDateString(),
            'status'                => SettlementStatus::OPEN->value,
            'total_payments_cents'  => 0,
            'total_refunds_cents'   => 0,
            'net_amount_cents'      => 0,
            'expected_amount_cents' => 150000,
            'variance_cents'        => 0,
            'version'               => 1,
        ]);

        // ── Search terms ──────────────────────────────────────────────────────
        // Auto-seeded by model observers when trips are created above.
        // Boost usage counts for demo plausibility.
        DB::table('search_terms')
            ->whereIn('term', ['cardiology', 'surgery', 'orthopedics', 'ophthalmology'])
            ->update(['usage_count' => DB::raw('usage_count + 20')]);
        DB::table('search_terms')
            ->whereIn('term', ['nairobi', 'lima', 'cairo', 'mumbai'])
            ->update(['usage_count' => DB::raw('usage_count + 10')]);

        $this->info('Done. Demo accounts:');
        $this->table(
            ['Username', 'Password', 'Role(s)'],
            [
                ['admin',    'Admin123!@',   'System Administrator'],
                ['drsmith',  'Doctor123!@',  'Doctor + Member (Approved)'],
                ['drjones',  'Doctor123!@',  'Doctor (Under Review)'],
                ['member1',  'Member123!@',  'Member (Standard membership)'],
                ['member2',  'Member123!@',  'Member'],
                ['reviewer', 'Review123!@',  'Credentialing Reviewer'],
                ['finance',  'Finance123!@', 'Finance Specialist'],
            ]
        );

        return self::SUCCESS;
    }

    private function makeUser(
        string $username,
        string $email,
        string $password,
        UserStatus $status,
        array $roles,
        string $firstName,
        string $lastName
    ): User {
        $user = User::create([
            'username' => $username,
            'email'    => $email,
            'password' => Hash::make($password),
            'status'   => $status->value,
            'version'  => 1,
        ]);

        UserProfile::create([
            'user_id'    => $user->id,
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ]);

        foreach ($roles as $role) {
            $user->addRole($role);
        }

        return $user->fresh();
    }
}
