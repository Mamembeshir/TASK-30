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
    public string $login    = '';  // accepts email address or username
    public string $password = '';
    public bool   $remember = false;

    /** Seconds remaining in lockout (populated on locked rejection). */
    public ?int $lockedSecondsRemaining = null;

    public function login(): void
    {
        $this->validate([
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $this->login)
                    ->orWhere('username', $this->login)
                    ->first();

        // Unknown credential → generic error (no user enumeration)
        if (! $user) {
            throw ValidationException::withMessages([
                'login' => 'The provided credentials do not match our records.',
            ]);
        }

        // AUTH-02: already locked → reject without incrementing count
        if ($user->isLocked()) {
            $this->lockedSecondsRemaining = (int) now()->diffInSeconds($user->locked_until, false);

            AuditService::record('user.login_blocked_locked', 'User', $user->id, null, [
                'locked_until' => $user->locked_until->toISOString(),
            ]);

            throw ValidationException::withMessages([
                'login' => 'Account is temporarily locked due to too many failed attempts.',
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
                    'login' => 'Too many failed attempts. Account locked for 15 minutes.',
                ]);
            }

            throw ValidationException::withMessages([
                'login' => 'The provided credentials do not match our records.',
            ]);
        }

        // AUTH-05: check account status
        if (! $user->status->canLogin()) {
            AuditService::record('user.login_blocked_status', 'User', $user->id, null, [
                'status' => $user->status->value,
            ]);

            throw ValidationException::withMessages([
                'login' => match ($user->status) {
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
        session()->regenerate();

        AuditService::record('user.login', 'User', $user->id, null, ['email' => $user->email]);

        // Full redirect (not navigate:true) — switches from guest to app layout
        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login', ['title' => 'Sign In']);
    }
}
