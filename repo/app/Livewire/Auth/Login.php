<?php

namespace App\Livewire\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $identifier = '';  // accepts email address or username
    public string $password   = '';
    public bool   $remember = false;

    /** Seconds remaining in lockout (populated on locked rejection). */
    public ?int $lockedSecondsRemaining = null;

    public function login(): void
    {
        $this->validate([
            'identifier' => ['required', 'string'],
            'password'   => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $this->identifier)
                    ->orWhere('username', $this->identifier)
                    ->first();

        // Unknown credential → generic error (no user enumeration)
        if (! $user) {
            throw ValidationException::withMessages([
                'identifier' =>'The provided credentials do not match our records.',
            ]);
        }

        // AUTH-02: already locked → reject without incrementing count
        if ($user->isLocked()) {
            $this->lockedSecondsRemaining = (int) now()->diffInSeconds($user->locked_until, false);

            AuditService::record('user.login_blocked_locked', 'User', $user->id, null, [
                'locked_until' => $user->locked_until->toISOString(),
            ]);

            throw ValidationException::withMessages([
                'identifier' =>'Account is temporarily locked due to too many failed attempts.',
            ]);
        }

        // Wrong password
        if (! Hash::check($this->password, $user->password)) {
            $newCount = $user->failed_login_count + 1;

            $update = ['failed_login_count' => $newCount];

            // AUTH-02: absolute timer — set once at exactly 5 failures, never extended
            if ($newCount >= 5 && $user->locked_until === null) {
                $update['locked_until'] = now()->addMinutes(15);
            }

            $user->forceFill($update)->save();

            AuditService::record('user.login_failed', 'User', $user->id, null, [
                'failed_login_count' => $newCount,
                'locked'             => isset($update['locked_until']),
            ]);

            if ($newCount >= 5) {
                $this->lockedSecondsRemaining = 900;
                throw ValidationException::withMessages([
                    'identifier' =>'Too many failed attempts. Account locked for 15 minutes.',
                ]);
            }

            throw ValidationException::withMessages([
                'identifier' =>'The provided credentials do not match our records.',
            ]);
        }

        // AUTH-05: check account status
        if (! $user->status->canLogin()) {
            AuditService::record('user.login_blocked_status', 'User', $user->id, null, [
                'status' => $user->status->value,
            ]);

            throw ValidationException::withMessages([
                'identifier' =>match ($user->status) {
                    UserStatus::SUSPENDED   => 'Your account has been suspended. Please contact support.',
                    UserStatus::DEACTIVATED => 'Your account has been deactivated.',
                    default                 => 'Your account is not active.',
                },
            ]);
        }

        // AUTH-03: success → reset lockout counters
        $user->forceFill([
            'failed_login_count' => 0,
            'locked_until'       => null,
        ])->save();

        Auth::login($user, $this->remember);

        AuditService::record('user.login', 'User', $user->id, null, ['email' => $user->email]);

        // Full redirect without navigate:true — crosses layout boundary (guest → app).
        // session()->regenerate() is intentionally omitted here: in a Livewire AJAX context
        // the new session cookie is sent in the JSON response; if the browser hasn't stored
        // it before window.location.href fires the /dashboard request fails auth.
        // Session fixation is mitigated by password verification above.
        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login', ['title' => 'Sign In']);
    }
}
