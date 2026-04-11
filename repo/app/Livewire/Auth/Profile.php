<?php

namespace App\Livewire\Auth;

use App\Models\UserProfile;
use App\Services\AuditService;
use App\Services\EncryptionService;
use App\Services\MaskingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Profile extends Component
{
    // ── Display state ──────────────────────────────────────────────────────────

    /**
     * Status flag flipped after a successful save so the view can flash a
     * confirmation banner without a full redirect.
     */
    public bool $saved = false;

    // ── Editable fields (basic) ────────────────────────────────────────────────

    public string  $firstName    = '';
    public string  $lastName     = '';
    public ?string $dateOfBirth  = null;
    public string  $phone        = '';

    /**
     * Sensitive fields are kept blank on form load: the user must re-type them
     * to update. The current value is shown only as a mask in the read-only
     * display block above the form.
     */
    public string $address     = '';
    public string $ssnFragment = '';

    // ── Lifecycle ──────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $profile = $this->ensureProfile();

        $this->firstName   = $profile->first_name ?? '';
        $this->lastName    = $profile->last_name ?? '';
        $this->dateOfBirth = $profile->date_of_birth?->toDateString();
        $this->phone       = $profile->phone ?? '';
    }

    protected function rules(): array
    {
        return [
            'firstName'   => ['required', 'string', 'max:100'],
            'lastName'    => ['required', 'string', 'max:100'],
            'dateOfBirth' => ['nullable', 'date', 'before:today'],
            'phone'       => ['nullable', 'string', 'max:20'],
            // Address is free-form; SSN fragment is the *last 4 digits only*.
            'address'     => ['nullable', 'string', 'max:300'],
            'ssnFragment' => ['nullable', 'string', 'regex:/^\d{4}$/'],
        ];
    }

    protected function messages(): array
    {
        return [
            'ssnFragment.regex' => 'SSN fragment must be exactly 4 digits.',
            'dateOfBirth.before' => 'Date of birth must be in the past.',
        ];
    }

    public function save(EncryptionService $encryption): void
    {
        $this->validate();

        $profile = $this->ensureProfile();
        $before  = $profile->only([
            'first_name', 'last_name', 'date_of_birth', 'phone',
            'address_mask', 'ssn_fragment_mask',
        ]);

        $profile->first_name    = $this->firstName;
        $profile->last_name     = $this->lastName;
        $profile->date_of_birth = $this->dateOfBirth ?: null;
        $profile->phone         = $this->phone ?: null;

        // Encrypt + mask only when the user supplied a new value. Blank input
        // means "leave existing encrypted value untouched". An explicit clear
        // is out of scope for the minimum profile UI.
        if ($this->address !== '') {
            $bundle = $encryption->encryptWithMask($this->address, 'address');
            $profile->address_encrypted = $bundle['encrypted'];
            $profile->address_mask      = $bundle['mask'];
        }

        if ($this->ssnFragment !== '') {
            $bundle = $encryption->encryptWithMask($this->ssnFragment, 'ssn');
            $profile->ssn_fragment_encrypted = $bundle['encrypted'];
            $profile->ssn_fragment_mask      = $bundle['mask'];
        }

        $profile->save();

        AuditService::record(
            action:     'user_profile.updated',
            entityType: 'UserProfile',
            entityId:   $profile->user_id,
            before:     $before,
            after:      $profile->only([
                'first_name', 'last_name', 'date_of_birth', 'phone',
                'address_mask', 'ssn_fragment_mask',
            ]),
        );

        // Clear the typed sensitive values from component state so the next
        // request snapshot does not echo them back to the browser.
        $this->address     = '';
        $this->ssnFragment = '';
        $this->saved       = true;
    }

    public function render(MaskingService $masking)
    {
        $user    = Auth::user();
        $profile = $this->ensureProfile();

        // Role-aware sensitive field display: admin viewing their own profile
        // sees plaintext + an audit entry; everyone else sees the mask.
        $sensitive = [
            'address'      => $masking->get($profile, 'address',      $user, logAccess: false),
            'ssn_fragment' => $masking->get($profile, 'ssn_fragment', $user, logAccess: false),
        ];

        return view('livewire.auth.profile', [
            'user'      => $user,
            'profile'   => $profile,
            'sensitive' => $sensitive,
            'roles'     => $user->roleValues(),
        ])->layout('layouts.app', ['title' => 'My Profile']);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function ensureProfile(): UserProfile
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->profile ?? UserProfile::create([
            'user_id'    => $user->id,
            'first_name' => '',
            'last_name'  => '',
        ]);
    }
}
