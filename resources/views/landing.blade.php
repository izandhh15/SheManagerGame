<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SheManagerGame — {{ __('landing.badge') }}</title>
    <meta name="description" content="{{ __('landing.subtitle') }}">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-surface-900 text-text-primary">
<div class="min-h-screen flex flex-col overflow-x-hidden">

    {{-- Nav --}}
    <header class="sticky top-0 z-50 bg-surface-900/90 backdrop-blur-md border-b border-border-default">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-purple-600 flex items-center justify-center shadow-lg shadow-purple-600/30">
                    <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M12 7.2l1.7 3.5 3.8.5-2.8 2.7.7 3.8-3.4-1.8-3.4 1.8.7-3.8-2.8-2.7 3.8-.5z"/>
                    </svg>
                </span>
                <span class="font-heading font-bold text-xl uppercase tracking-wide">SheManager<span class="text-purple-400">Game</span></span>
            </a>
            <nav class="flex items-center gap-2 sm:gap-3">
                <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700 transition-colors">{{ __('landing.cta_login') }}</a>
                <a href="{{ route('register') }}" class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-lg text-sm font-semibold bg-purple-600 text-white hover:bg-purple-500 shadow-lg shadow-purple-600/30 transition-colors">{{ __('landing.cta_play') }}</a>
            </nav>
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[900px] h-[500px] bg-purple-600/20 blur-[140px] rounded-full"></div>
            <div class="absolute top-40 -left-40 w-[400px] h-[400px] bg-accent-blue/10 blur-[120px] rounded-full"></div>
        </div>
        <div class="relative max-w-7xl mx-auto px-4 pt-16 sm:pt-24 pb-14 text-center">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-purple-600/15 border border-purple-500/30 text-purple-300 text-xs sm:text-sm font-semibold uppercase tracking-widest">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                {{ __('landing.badge') }}
            </span>
            <h1 class="font-heading font-extrabold uppercase tracking-tight text-5xl sm:text-6xl lg:text-7xl mt-6 leading-[0.95]">
                {{ __('landing.title_1') }}<br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-purple-500 to-accent-blue">{{ __('landing.title_2') }}</span>
            </h1>
            <p class="max-w-2xl mx-auto mt-6 text-base sm:text-lg text-text-secondary leading-relaxed">
                {{ __('landing.subtitle') }}
            </p>
            <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('register') }}" class="w-full sm:w-auto px-8 py-4 rounded-xl text-base font-bold bg-purple-600 text-white hover:bg-purple-500 shadow-xl shadow-purple-600/30 transition-all hover:-translate-y-0.5">
                    {{ __('landing.cta_play') }}
                </a>
                <a href="{{ route('login') }}" class="w-full sm:w-auto px-8 py-4 rounded-xl text-base font-semibold border border-border-strong text-text-body hover:bg-surface-700 transition-colors">
                    {{ __('landing.cta_login') }}
                </a>
            </div>
            <p class="mt-4 text-xs text-text-muted">{{ __('landing.cta_note') }}</p>

            {{-- Floating competition logos --}}
            @php
                $heroLogos = ['ESP1','ENG1','UCL','BRA1','FRA1','LIBERTADORES','USA1','DEU1','WWCU27','ITA1','MEX1','ARG1'];
            @endphp
            <div class="mt-12 flex flex-wrap items-center justify-center gap-3 sm:gap-4">
                @foreach($heroLogos as $logoId)
                    <img src="{{ \App\Support\CompetitionLogos::url($logoId) }}" alt="{{ $logoId }}" class="w-11 h-11 sm:w-14 sm:h-14 rounded-xl shadow-lg hover:scale-110 transition-transform" loading="lazy">
                @endforeach
            </div>
        </div>
    </section>

    {{-- Stats (computed from real game data via LandingStats, cached) --}}
    <section class="border-y border-border-default bg-surface-800/50">
        <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-2 lg:grid-cols-4 gap-6 text-center">
            <div>
                <p class="font-heading text-4xl sm:text-5xl font-extrabold text-purple-400">{{ \Illuminate\Support\Number::format($landingStats['countries']) }}</p>
                <p class="mt-1 text-sm text-text-muted uppercase tracking-widest">{{ __('landing.stats_countries') }}</p>
            </div>
            <div>
                <p class="font-heading text-4xl sm:text-5xl font-extrabold text-purple-400">{{ \Illuminate\Support\Number::format($landingStats['competitions']) }}+</p>
                <p class="mt-1 text-sm text-text-muted uppercase tracking-widest">{{ __('landing.stats_competitions') }}</p>
            </div>
            <div>
                <p class="font-heading text-4xl sm:text-5xl font-extrabold text-purple-400">{{ \Illuminate\Support\Number::format($landingStats['teams']) }}+</p>
                <p class="mt-1 text-sm text-text-muted uppercase tracking-widest">{{ __('landing.stats_teams') }}</p>
            </div>
            <div>
                <p class="font-heading text-4xl sm:text-5xl font-extrabold text-purple-400">{{ \Illuminate\Support\Number::format($landingStats['players']) }}+</p>
                <p class="mt-1 text-sm text-text-muted uppercase tracking-widest">{{ __('landing.stats_players') }}</p>
            </div>
        </div>
    </section>

    {{-- Leagues --}}
    <section class="max-w-7xl mx-auto px-4 py-16 sm:py-20 w-full">
        <h2 class="font-heading text-2xl sm:text-3xl font-bold uppercase tracking-wide text-center">{{ __('landing.leagues_title') }}</h2>
        @php
            $leagueRows = [
                ['ESP1' => 'Liga F', 'ENG1' => 'WSL', 'FRA1' => 'Première Ligue', 'DEU1' => 'Frauen-Bundesliga', 'ITA1' => 'Serie A Women', 'NED1' => 'Eredivisie'],
                ['BRA1' => 'Brasileirão', 'USA1' => 'NWSL', 'MEX1' => 'Liga MX Femenil', 'ARG1' => 'Primera A', 'POR1' => 'Liga BPI', 'SUI1' => 'Super League'],
                ['UCL' => 'Champions League', 'UEL' => 'Europa Cup', 'LIBERTADORES' => 'Libertadores', 'CONCACHAMPIONS' => 'W Champions Cup', 'WWCU27' => 'Mundial 2027', 'WEURO' => 'Eurocopa'],
            ];
        @endphp
        <div class="mt-8 space-y-3">
            @foreach($leagueRows as $row)
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    @foreach($row as $logoId => $label)
                        <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-surface-800 border border-border-default hover:border-purple-500/50 transition-colors">
                            <img src="{{ \App\Support\CompetitionLogos::url($logoId) }}" alt="{{ $label }}" class="w-10 h-10 shrink-0" loading="lazy">
                            <span class="text-sm font-medium text-text-body truncate">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    {{-- Features --}}
    <section class="border-t border-border-default bg-surface-800/30">
        <div class="max-w-7xl mx-auto px-4 py-16 sm:py-20">
            <h2 class="font-heading text-2xl sm:text-3xl font-bold uppercase tracking-wide text-center">{{ __('landing.features_title') }}</h2>
            <p class="text-center text-text-muted mt-2">{{ __('landing.features_sub') }}</p>
            @php
                $features = [
                    ['f1_title', 'f1_desc', 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 00-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0'],
                    ['f2_title', 'f2_desc', 'M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.048-.599V4.072a48.484 48.484 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a9 9 0 016.208.677l.108.055a9 9 0 006.086.71l3.114-.732'],
                    ['f3_title', 'f3_desc', 'M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5'],
                    ['f4_title', 'f4_desc', 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75m-8.25-8.25l-.75-.75m-.75.75l.75-.75m.75.75l-.75.75M12 15.75h.375a.375.375 0 00.375-.375V14.25m-1.5 1.5H12m0 0v2.25'],
                    ['f5_title', 'f5_desc', 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 00-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0'],
                    ['f6_title', 'f6_desc', 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z'],
                ];
            @endphp
            <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($features as [$t, $d, $path])
                    <div class="p-6 rounded-2xl bg-surface-800 border border-border-default hover:border-purple-500/40 transition-colors">
                        <div class="w-12 h-12 rounded-xl bg-purple-600/15 border border-purple-500/30 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                        </div>
                        <h3 class="font-heading font-bold text-lg uppercase tracking-wide">{{ __("landing.$t") }}</h3>
                        <p class="mt-2 text-sm text-text-secondary leading-relaxed">{{ __("landing.$d") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[800px] h-[300px] bg-purple-600/20 blur-[120px] rounded-full"></div>
        </div>
        <div class="relative max-w-3xl mx-auto px-4 py-16 sm:py-24 text-center">
            <h2 class="font-heading text-3xl sm:text-4xl font-extrabold uppercase tracking-tight">{{ __('landing.cta2_title') }}</h2>
            <p class="mt-3 text-text-secondary">{{ __('landing.cta2_sub') }}</p>
            <a href="{{ route('register') }}" class="inline-block mt-8 px-10 py-4 rounded-xl text-base font-bold bg-purple-600 text-white hover:bg-purple-500 shadow-xl shadow-purple-600/30 transition-all hover:-translate-y-0.5">
                {{ __('landing.cta2_button') }}
            </a>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-border-default">
        <div class="max-w-7xl mx-auto px-4 py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-purple-600 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.2l1.7 3.5 3.8.5-2.8 2.7.7 3.8-3.4-1.8-3.4 1.8.7-3.8-2.8-2.7 3.8-.5z"/></svg>
                </span>
                <span class="text-sm text-text-muted">{{ __('landing.footer_tagline') }}</span>
            </div>
            <div class="flex items-center gap-4 text-xs text-text-muted">
                <span>{{ __('landing.footer_data') }}</span>
                <a href="https://github.com/izandhh15/SheManagerGame" target="_blank" rel="noopener" class="hover:text-text-secondary">GitHub</a>
                <a href="https://instagram.com/shemanagergame" target="_blank" rel="noopener" class="hover:text-text-secondary">Instagram</a>
            </div>
        </div>
    </footer>
</div>
</body>
</html>
