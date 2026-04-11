<?php

namespace App\Livewire\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Register extends Component
{
    public string $username              = '';
    public string $email                 = '';
    public string $first_name            = '';
    public string $last_name             = '';
    public string $password              = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $this->validate([
            'username'   => ['required', 'string', 'min:3', 'max:150', 'unique:users,username', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            // AUTH-01: min 10 chars, 1 upper, 1 lower, 1 digit, 1 special.
            // NOTE: `->uncompromised()` is intentionally NOT used. It performs
            // an outbound HTTPS call to the HaveIBeenPwned range API, which
            // violates the "no internet at runtime" constraint (see
            // docs/claude.md "What NOT to Do" → "No external API calls at
            // runtime"). Local complexity rules are the authoritative check
            // for this offline deployment. If a future build gains network
            // access, re-enabling the breach check belongs behind a
            // `medvoyage.auth.check_compromised_passwords` config flag that
            // defaults to false.
            'password'   => [
                'required',
                'confirmed',
                Password::min(10)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $user = User::create([
            'username' => $this->username,
            'email'    => $this->email,
            'password' => $this->password,
            'status'   => UserStatus::ACTIVE,
            'version'  => 1,
        ]);

        UserProfile::create([
            'user_id'    => $user->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
        ]);

        $user->addRole(UserRole::MEMBER);

        AuditService::record('user.registered', 'User', $user->id, null, ['username' => $user->username]);

        Auth::login($user);
        session()->regenerate();
        // Full redirect (not navigate:true) — switches from guest to app layout
        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.register', ['title' => 'Create Account']);
    }
}
