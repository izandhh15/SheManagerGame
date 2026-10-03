<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0B1120">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <!-- FOUC prevention: apply saved theme before paint -->
        <script>(function(){var t=localStorage.getItem('virtua-theme');if(t==='light'){document.documentElement.classList.add('light');document.querySelector('meta[name=theme-color]')?.setAttribute('content','#ffffff');}})()</script>

        <!-- Fonts (loaded via CSS @import in app.css) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface-900 text-text-primary">
        <div class="min-h-screen flex flex-col">

            @if(session('impersonating_from'))
                <div class="bg-rose-500 text-white text-center text-xs py-1.5 px-4 flex items-center justify-center gap-3">
                    <span>{{ __('admin.impersonating_banner', ['name' => auth()->user()->name, 'email' => auth()->user()->email]) }}</span>
                    <form method="POST" action="{{ route('admin.stop-impersonation') }}" class="inline">
                        @csrf
                        <x-ghost-button type="submit" color="slate" class="underline font-semibold text-white hover:text-rose-100">{{ __('admin.stop_impersonating') }}</x-ghost-button>
                    </form>
                </div>
            @endif

            <x-beta-banner />

            {{-- Site header (desktop nav + mobile hamburger). Game views render
                 their own game-header through the $header slot, so this only
                 shows on non-game pages (dashboard, new game, rankings…). --}}
            @unless(isset($header))
            <header x-data="{ mobileOpen: false, accountOpen: false }" class="sticky top-0 z-50 bg-surface-900/95 backdrop-blur-md border-b border-border-default">
                <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between gap-3">
                    <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="flex items-center gap-2 shrink-0">
                        <span class="w-8 h-8 rounded-lg bg-purple-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                <path d="M12 7.2l1.7 3.5 3.8.5-2.8 2.7.7 3.8-3.4-1.8-3.4 1.8.7-3.8-2.8-2.7 3.8-.5z"/>
                            </svg>
                        </span>
                        <span class="font-heading font-bold text-lg uppercase tracking-wide text-text-primary">SheManager<span class="text-purple-400">Game</span></span>
                    </a>

                    {{-- Desktop nav --}}
                    <nav class="hidden md:flex items-center gap-1">
                        @auth
                            <a href="{{ route('select-team') }}" class="px-3 py-2 rounded-lg text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700 transition-colors">{{ __('app.new_game') }}</a>
                            <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded-lg text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700 transition-colors">{{ __('app.my_games') }}</a>
                            <a href="{{ route('leaderboard') }}" class="px-3 py-2 rounded-lg text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700 transition-colors">{{ __('leaderboard.title') }}</a>
                            <a href="{{ route('friends.index') }}" class="px-3 py-2 rounded-lg text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700 transition-colors">👥 {{ __('friends.title') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="px-3 py-2 rounded-lg text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700 transition-colors">{{ __('app.login') }}</a>
                            <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg text-sm font-semibold bg-accent-red text-white hover:bg-accent-red/90 transition-colors">{{ __('app.register') }}</a>
                        @endauth
                    </nav>

                    <div class="hidden md:flex items-center gap-2">
                        <x-theme-toggle />
                        @auth
                            <div class="relative">
                                <button @click="accountOpen = !accountOpen" @click.outside="accountOpen = false" class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-full hover:bg-surface-700 transition-colors">
                                    <span class="w-8 h-8 rounded-full bg-accent-blue/20 text-accent-blue flex items-center justify-center text-sm font-bold">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                                    <svg class="w-4 h-4 text-text-muted transition-transform" :class="accountOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="accountOpen" x-cloak x-transition class="absolute right-0 mt-2 w-56 bg-surface-800 rounded-xl shadow-xl border border-border-strong py-1.5 z-50">
                                    <div class="px-4 py-2.5 border-b border-border-default">
                                        <p class="text-sm font-semibold text-text-primary truncate">{{ auth()->user()->name }}</p>
                                        <p class="text-xs text-text-muted truncate">{{ auth()->user()->email }}</p>
                                    </div>
                                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm text-text-body hover:bg-surface-700">{{ __('app.profile') }}</a>
                                    @if(auth()->user()->is_admin)
                                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2.5 text-sm text-text-body hover:bg-surface-700">{{ __('app.admin') }}</a>
                                    @endif
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-accent-red hover:bg-surface-700">{{ __('app.log_out') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endauth
                    </div>

                    {{-- Mobile hamburger --}}
                    <div class="flex md:hidden items-center gap-1">
                        <x-theme-toggle />
                        <button @click="mobileOpen = !mobileOpen" class="p-2.5 -mr-2 rounded-lg hover:bg-surface-700 transition-colors" :aria-label="__('app.menu')">
                            <svg x-show="!mobileOpen" class="w-6 h-6 text-text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            <svg x-show="mobileOpen" x-cloak class="w-6 h-6 text-text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Mobile panel --}}
                <div x-show="mobileOpen" x-cloak
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
                     class="md:hidden border-t border-border-default bg-surface-900">
                    <nav class="px-4 py-3 space-y-1 max-h-[70vh] overflow-y-auto">
                        @auth
                            <div class="flex items-center gap-3 px-3 py-3 border-b border-border-default mb-2">
                                <span class="w-10 h-10 rounded-full bg-accent-blue/20 text-accent-blue flex items-center justify-center font-bold">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-text-primary truncate">{{ auth()->user()->name }}</p>
                                    <p class="text-xs text-text-muted truncate">{{ auth()->user()->email }}</p>
                                </div>
                            </div>
                            <a href="{{ route('select-team') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium text-text-body hover:bg-surface-700">
                                <svg class="w-5 h-5 text-accent-red" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                {{ __('app.new_game') }}
                            </a>
                            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium text-text-body hover:bg-surface-700">
                                <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                                {{ __('app.my_games') }}
                            </a>
                            <a href="{{ route('leaderboard') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium text-text-body hover:bg-surface-700">
                                <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 01-5.54 0"/></svg>
                                {{ __('leaderboard.title') }}
                            </a>
                            <a href="{{ route('friends.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium text-text-body hover:bg-surface-700">
                                <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                                👥 {{ __('friends.title') }}
                            </a>
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium text-text-body hover:bg-surface-700">
                                <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                {{ __('app.profile') }}
                            </a>
                            @if(auth()->user()->is_admin)
                                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium text-text-body hover:bg-surface-700">
                                    <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                                    {{ __('app.admin') }}
                                </a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium text-accent-red hover:bg-surface-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                    {{ __('app.log_out') }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium text-text-body hover:bg-surface-700">
                                <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                {{ __('app.login') }}
                            </a>
                            <a href="{{ route('register') }}" class="flex items-center justify-center gap-2 mx-3 mt-2 px-4 py-3 rounded-xl text-sm font-semibold bg-accent-red text-white hover:bg-accent-red/90">
                                {{ __('app.register') }}
                            </a>
                        @endauth
                    </nav>
                </div>
            </header>
            @endunless

            <!-- Page Heading -->
            @isset($header)
                <header>
                    <div class="max-w-7xl mx-auto p-4 pb-0 lg:pb-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="text-text-body flex-1 pb-24 lg:pb-0">
                {{ $slot }}
            </main>
            @unless($hideFooter ?? false)
            <footer class="hidden lg:block mt-12 bg-surface-800/40">
                <div class="border-t border-border-default/50">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                            {{-- Logo + copyright --}}
                            <div class="flex flex-col items-center md:items-start gap-3">
                                <a href="{{ route('dashboard') }}" class="font-heading font-bold text-lg uppercase tracking-wide text-text-primary">SheManager<span class="text-purple-400">Game</span></a>
                                <p class="text-xs text-text-faint">
                                    &copy; {{ date('Y') }} Izan Delgado &middot; <a href="https://github.com/izandhh15/SheManagerGame" target="_blank" class="hover:text-text-muted transition-colors">Proyecto Open Source</a> &middot; <a href="{{ route('legal') }}" class="hover:text-text-muted transition-colors">Aviso Legal</a> &middot; <a href="https://instagram.com/shemanagergame" target="_blank" rel="noopener" class="hover:text-text-muted transition-colors">Instagram</a>
                                </p>
                                <p class="text-xs text-text-faint">
                                    {{ __('app.data_attribution_prefix') }} <a href="https://www.soccerdonna.de" target="_blank" rel="noopener" class="hover:text-text-muted transition-colors">Soccerdonna</a>{{ __('app.data_attribution_suffix') }}
                                </p>
                            </div>

                            {{-- Navigation links --}}
                            <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs text-text-muted">
                                @if(auth()->user())
                                <a href="{{ route('select-team') }}" class="hover:text-text-secondary transition-colors">{{ __('app.new_game') }}</a>
                                <a href="{{ route('dashboard') }}" class="hover:text-text-secondary transition-colors">{{ __('app.load_game') }}</a>
                                <a href="{{ route('leaderboard') }}" class="hover:text-text-secondary transition-colors">{{ __('leaderboard.title') }}</a>

                                {{-- Account dropdown --}}
                                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                    <button @click="open = !open" class="flex items-center gap-1 hover:text-text-secondary transition-colors cursor-pointer">
                                        {{ __('app.account') }}
                                        <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                    <div x-show="open"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="opacity-100 scale-100"
                                        x-transition:leave-end="opacity-0 scale-95"
                                        class="absolute bottom-full mb-2 right-0 w-40 rounded-lg shadow-xl origin-bottom-right z-50"
                                        style="display: none;"
                                        @click="open = false">
                                        <div class="rounded-lg py-1 bg-surface-800 border border-border-strong">
                                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-xs text-text-muted hover:text-text-secondary hover:bg-surface-700 transition-colors">{{ __('app.edit_profile') }}</a>
                                            @if(auth()->user()?->is_admin)
                                                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-xs text-text-muted hover:text-text-secondary hover:bg-surface-700 transition-colors">Admin</a>
                                            @endif
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button type="submit" class="w-full text-left px-4 py-2 text-xs text-text-muted hover:text-text-secondary hover:bg-surface-700 transition-colors cursor-pointer">{{ __('app.log_out') }}</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                <x-theme-toggle />
                            </nav>
                        </div>
                    </div>
                </div>
            </footer>
            @endunless
        </div>
        <!-- Cloudflare Web Analytics --><script defer src='https://static.cloudflareinsights.com/beacon.min.js' data-cf-beacon='{"token": "cd6b25d4492f4012bfd641f2dcbaccaa"}'></script><!-- End Cloudflare Web Analytics -->
    </body>
</html>
