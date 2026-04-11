<x-card>
    <form wire:submit="login" class="space-y-5">
        <div class="text-center mb-6">
            <h2 class="text-xl font-semibold text-[#111827] font-display">Sign in to your account</h2>
        </div>

        {{-- Lockout warning --}}
        @if($lockedSecondsRemaining)
            <div
                x-data="{ seconds: {{ $lockedSecondsRemaining }} }"
                x-init="
                    let t = setInterval(() => {
                        if (--seconds <= 0) { seconds = 0; clearInterval(t); }
                    }, 1000)
                "
                class="rounded-lg bg-[#FEF2F2] border border-[#FECACA] px-4 py-3 text-sm text-[#DC2626] flex items-start gap-2"
            >
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <div>
                    Account locked.
                    <span x-show="seconds > 0">
                        Try again in
                        <span class="font-mono font-semibold" x-text="Math.floor(seconds/60).toString().padStart(2,'0') + ':' + (seconds%60).toString().padStart(2,'0')"></span>.
                    </span>
                    <span x-show="seconds <= 0">You may try again now.</span>
                </div>
            </div>
        @endif

        <x-input
            label="Email or username"
            type="text"
            wire:model="login"
            :error="$errors->first('login')"
            required
            autocomplete="username"
            placeholder="you@example.com or your_username"
        />

        <x-input
            label="Password"
            type="password"
            wire:model="password"
            :error="$errors->first('password')"
            required
            autocomplete="current-password"
        />

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-[#4B5563] cursor-pointer">
                <input type="checkbox" wire:model="remember" class="rounded border-[#D1D5DB] text-[#1B6B93] focus:ring-[#1B6B93]"/>
                Remember me
            </label>
        </div>

        <x-button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" :loading="false">
            <span wire:loading.remove>Sign in</span>
            <span wire:loading>Signing in…</span>
        </x-button>

        <p class="text-center text-sm text-[#4B5563]">
            Don't have an account?
            <a href="{{ route('register') }}" class="text-[#1B6B93] font-medium hover:underline">Register</a>
        </p>
    </form>
</x-card>
