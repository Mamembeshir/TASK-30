<?php

namespace App\Livewire\Auth;

use App\Models\UserProfile;
use App\Services\ApiClient;
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

    public function save(): void
    {
        $this->validate();

        $body = [
            'first_name'   => $this->firstName,
            'last_name'    => $this->lastName,
        ];

        if ($this->dateOfBirth !== null && $this->dateOfBirth !== '') {
            $body['date_of_birth'] = $this->dateOfBirth;
        }
        if ($this->phone !== '') {
            $body['phone'] = $this->phone;
        }
        if ($this->address !== '') {
            $body['address'] = $this->address;
        }
        if ($this->ssnFragment !== '') {
            $body['ssn_fragment'] = $this->ssnFragment;
        }

        $response = app(ApiClient::class)->put('/profile', $body);

        if ($response->status() >= 400) {
            $this->addError('form', $response->json('message') ?? 'Failed to save profile.');
            return;
        }

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
