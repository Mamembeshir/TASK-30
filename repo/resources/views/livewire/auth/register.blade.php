<x-card>
    <form wire:submit="register" class="space-y-5">
        <div class="text-center mb-6">
            <h2 class="text-xl font-semibold text-[#111827] font-display">Create your account</h2>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <x-input label="First name" wire:model="first_name" :error="$errors->first('first_name')" required />
            <x-input label="Last name"  wire:model="last_name"  :error="$errors->first('last_name')"  required />
        </div>

        <x-input label="Username"      type="text"     wire:model="username" :error="$errors->first('username')" required
                 help-text="Letters, numbers, dots, hyphens, underscores only." />
        <x-input label="Email address" type="email"    wire:model="email"    :error="$errors->first('email')"    required />
        <x-input label="Password"      type="password" wire:model="password" :error="$errors->first('password')" required />
        <x-input label="Confirm password" type="password" wire:model="password_confirmation"
                 :error="$errors->first('password_confirmation')" required />

        <x-button type="submit" variant="primary" class="w-full">Create account</x-button>

        <p class="text-center text-sm text-[#4B5563]">
            Already have an account?
            <a href="{{ route('login') }}" class="text-[#1B6B93] font-medium hover:underline">Sign in</a>
        </p>
    </form>
</x-card>
