<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'MedVoyage') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-[#FAFBFC] font-sans antialiased">

<div class="flex h-full" x-data>

    {{-- Sidebar --}}
    @auth
        <x-sidebar />
    @endauth

    {{-- Main column --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        {{-- Top bar --}}
        <header class="flex-shrink-0 h-16 bg-white border-b border-[#E5E7EB] flex items-center px-6 gap-4">

            {{-- Mobile hamburger --}}
            <button
                type="button"
                class="lg:hidden p-2 rounded-lg text-[#4B5563] hover:bg-[#F1F4F8] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1B6B93]"
                x-on:click="$dispatch('toggle-sidebar')"
                aria-label="Toggle sidebar"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- Page title (from slot) --}}
            <div class="flex-1 min-w-0">
                @isset($header)
                    {{ $header }}
                @endisset
            </div>

            {{-- Top-right actions --}}
            <div class="flex items-center gap-3">

                {{-- Search --}}
                <a
                    href="{{ route('search') }}"
                    class="p-2 rounded-lg text-[#4B5563] hover:bg-[#F1F4F8] transition-colors"
                    aria-label="Search"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </a>

                {{-- Logout --}}
                @auth
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="p-2 rounded-lg text-[#4B5563] hover:bg-[#F1F4F8] transition-colors"
                        aria-label="Sign out"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
                @endauth
            </div>
        </header>

        {{-- Livewire top progress bar --}}
        <div
            x-data
            x-on:livewire:navigating.window="$el.style.width = '0%'; $el.style.opacity = '1'"
            x-on:livewire:navigated.window="$el.style.width = '100%'; setTimeout(() => $el.style.opacity = '0', 200)"
            class="h-[2px] bg-[#1B6B93] transition-all duration-300 ease-out opacity-0"
            style="width: 0%"
        ></div>

        {{-- Page content --}}
        <main class="flex-1 overflow-y-auto">
            <div class="max-w-[1280px] mx-auto px-8 py-8 max-md:px-4">

                {{-- Flash messages --}}
                <div class="fixed top-4 right-4 z-50 space-y-2" id="toast-container">
                    @if(session('success'))
                        <x-toast type="success" :message="session('success')"/>
                    @endif
                    @if(session('error'))
                        <x-toast type="danger" :message="session('error')"/>
                    @endif
                    @if(session('warning'))
                        <x-toast type="warning" :message="session('warning')"/>
                    @endif
                    @if(session('info'))
                        <x-toast type="info" :message="session('info')"/>
                    @endif
                </div>

                {{ $slot }}
            </div>
        </main>
    </div>
</div>

@livewireScripts
</body>
</html>
