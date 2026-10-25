{{--
    Next-gen game shell — FASE 1 (rama next-gen-menus).

    Sidebar en escritorio + barra inferior en móvil, identidad morada de
    SheManagerGame. Sustituye al header superior + menús desplegables del
    layout viejo, pero NO cambia la lógica de ninguna pantalla: cada enlace
    apunta a una ruta EXISTENTE, que sigue renderizando su vista con el
    layout viejo hasta que se migre.

    Pantallas migradas en fase 1:
      - dashboard.blade.php (Mis partidas, sin juego: nav global)
      - game-hub-nextgen.blade.php (nuevo menú del juego, ruta game.menu)
    Pantallas migradas en fase 2 (rama next-gen-full): TODAS las
    restantes, 66 vistas de página completa, solo cambio de etiquetas
    de layout (sin tocar lógica, rutas, datos ni contenido; los
    <x-slot name="header"> se conservan tal cual; hide-footer
    eliminado porque el nuevo shell no lo usa):
      - Con juego (:game="$game ?? null"): game (panel clásico),
        squad, squad-planner, squad-academy, squad-registration,
        squad-reserve, squad-selection, lineup, calendar,
        calendar-month, transfer-market, incoming-transfers,
        outgoing-transfers, transfer-activity, scouting-hub,
        youth-scouting, explore, club/finances, club/stadium,
        club/commercial, club/investment, club/reputation,
        club/matchday, internet, social-feed, club-social,
        national-social, press-conference, pre-match-press,
        national-squad-picker, national-squad-preview,
        national-venues, manager-career, job-market, season-offers,
        season-end, new-season, preseason-setup, cup, standings,
        swiss-standings, group-stage-cup, results,
        opponent-analysis, schedule-friendly, government-friendly,
        government-venue, tournament-end, tournament-simulation,
        tournament-summary, welcome-tournament, welcome-tutorial,
        fast-mode, game-loading, game-loading-matchday.
      - Globales sin juego (<x-game-nextgen-layout> sin :game):
        leaderboard, leaderboard/national-team,
        leaderboard/national-teams-index, leaderboard/team,
        leaderboard/teams-index, leaderboard/tournament,
        friends/index, friends/careers, profile/edit, profile/show,
        select-team.
      - national-squad-picker/preview usan :game="$game ?? null"
        porque comparten vista entre nueva partida (sin juego) y
        partida en curso (con juego); el ?? null cubre ambos casos.
    Pendiente de migrar: NINGUNA vista de página completa. Quedan
    solo menciones no migratables: layouts/app.blade.php (el layout
    viejo, se conserva), este comentario, y el ejemplo escapado en
    design-system/sections/layout-patterns.blade.php (documentación,
    no una vista).
    No tocar UnderConstruction ni bootstrap/app.php: este shell solo se ve
    cuando el juego vuelve a estar abierto (o en local con el middleware
    desactivado temporalmente, revertido antes de commitear).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6D28D9">
    <title>{{ config('app.name', 'SheManagerGame') }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <script>(function(){var t=localStorage.getItem('virtua-theme');if(t==='light'){document.documentElement.classList.add('light');document.querySelector('meta[name=theme-color]')?.setAttribute('content','#ffffff');}})()</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-surface-900 text-text-primary">
<div class="min-h-screen lg:flex">

    @php
        $currentRoute = Route::currentRouteName();
        $isGame = $game !== null;
        $isCareer = $isGame && $game->isCareerMode();
        $isNational = $isGame && $game->team?->type === 'national';
        $isPro = $isGame && $game->isProManagerMode();
        $gid = $isGame ? [$game->id] : [];

        // Sidebar sections: [section title, items]. Each item: route, label, params.
        // Only routes that exist in routes/web.php are listed here.
        // REDISEÑO 25-10-2026 (petición de Izan: "los menús siguen siendo
        // los mismos"): agrupación, orden y nombres nuevos, y entradas
        // que el menú viejo no tenía (Rival, Día de partido, Actividad).
        // Solo cambia la presentación: cada enlace sigue apuntando a
        // una ruta EXISTENTE, sin tocar lógica, datos ni contenido.
        $sections = [];
        if ($isGame) {
            $play = [
                ['route' => 'game.menu', 'label' => '🧭 Vestuario general', 'params' => $gid],
                ['route' => 'show-game', 'label' => '🏠 Panel', 'params' => $gid],
                ['route' => 'game.calendar', 'label' => '📅 ' . __('app.calendar'), 'params' => $gid],
            ];
            if ($game->next_match) {
                $play[] = ['route' => 'game.lineup', 'label' => '⚽ ' . __('app.starting_xi'), 'params' => $gid];
                $play[] = ['route' => 'game.opponent-analysis', 'label' => '🔍 Rival', 'params' => $gid];
            }
            $sections[] = ['⚽ El partido', $play];

            $dressing = [
                ['route' => 'game.squad', 'label' => '👥 ' . __('app.squad'), 'params' => $gid],
            ];
            if ($isCareer) {
                $dressing[] = ['route' => 'game.squad.planner', 'label' => '📋 ' . __('app.tactics'), 'params' => $gid];
                if (! $game->isFilial()) {
                    $dressing[] = ['route' => 'game.squad.academy', 'label' => '🌱 ' . __('squad.academy'), 'params' => $gid];
                }
                $dressing[] = ['route' => 'game.squad.registration', 'label' => '📝 ' . __('squad.registration'), 'params' => $gid];
                if ($game->isFilial()) {
                    $dressing[] = ['route' => 'game.squad.reserve', 'label' => '👥 ' . __('squad.reserve_team'), 'params' => $gid];
                }
            }
            $sections[] = ['👥 Vestuario', $dressing];

            if ($isCareer) {
                $sections[] = ['🔄 Mercado', [
                    ['route' => 'game.transfers.market', 'label' => '🛒 ' . __('transfers.market_tab'), 'params' => $gid],
                    ['route' => 'game.transfers', 'label' => '⬇️ ' . __('transfers.incoming'), 'params' => $gid],
                    ['route' => 'game.transfers.outgoing', 'label' => '⬆️ ' . __('transfers.outgoing'), 'params' => $gid],
                    ['route' => 'game.transfer-activity', 'label' => '📰 Actividad', 'params' => $gid],
                    ['route' => 'game.scouting', 'label' => '🔭 ' . __('transfers.scouting_tab'), 'params' => $gid],
                    ['route' => 'game.explore', 'label' => '🌍 ' . __('transfers.explore_tab'), 'params' => $gid],
                ]];
                $sections[] = ['🏟️ El club', [
                    ['route' => 'game.club.finances', 'label' => '💰 ' . __('club.nav.finances'), 'params' => $gid],
                    ['route' => 'game.club.stadium', 'label' => '🏟️ ' . __('club.nav.stadium'), 'params' => $gid],
                    ['route' => 'game.club.matchday', 'label' => '🎟️ Día de partido', 'params' => $gid],
                    ['route' => 'game.club.commercial', 'label' => '🤝 ' . __('club.nav.commercial'), 'params' => $gid],
                    ['route' => 'game.club.investment', 'label' => '📈 ' . __('club.nav.investment'), 'params' => $gid],
                    ['route' => 'game.club.reputation', 'label' => '⭐ ' . __('club.nav.reputation'), 'params' => $gid],
                ]];
            }

            $social = [
                ['route' => 'game.internet', 'label' => '🌐 ' . __('game.internet_title'), 'params' => $gid],
                ['route' => 'game.social', 'label' => '🐦 ' . __('game.nav_social'), 'params' => $gid],
                ['route' => 'game.club-social', 'label' => '📢 ' . __('game.nav_club_social'), 'params' => $gid],
            ];
            if ($isNational) {
                $social[] = ['route' => 'game.national-social', 'label' => '📢 ' . __('game.nav_national_social'), 'params' => $gid];
            }
            $sections[] = ['📣 Grada y prensa', $social];

            if ($isNational) {
                $sections[] = [__('app.selection'), [
                    ['route' => 'game.national-squad-picker', 'label' => __('game.convocatoria'), 'params' => $gid],
                    ['route' => 'game.national-venues', 'label' => '🏟️ Sedes', 'params' => $gid],
                ]];
            }

            if ($isPro) {
                $sections[] = ['💼 Carrera', [
                    ['route' => 'game.manager.career', 'label' => __('manager.career_title'), 'params' => $gid],
                    ['route' => 'game.job-market', 'label' => '💼 Mercado de trabajo', 'params' => $gid],
                ]];
            }
        }

        $globalItems = [
            ['route' => 'dashboard', 'label' => '🗂️ ' . __('app.my_games'), 'params' => []],
            ['route' => 'select-team', 'label' => '➕ ' . __('app.new_game'), 'params' => []],
            ['route' => 'leaderboard', 'label' => '🏆 ' . __('leaderboard.title'), 'params' => []],
            ['route' => 'friends.index', 'label' => '👥 ' . __('friends.title'), 'params' => []],
            ['route' => 'profile.edit', 'label' => '👤 ' . __('app.profile'), 'params' => []],
        ];

        // Mobile bottom bar: max 5 taps, all existing routes.
        // REDISEÑO 25-10-2026: el primer botón es Jugar, no el menú.
        $mobileItems = $isGame
            ? array_filter([
                $game->next_match ? ['route' => 'game.lineup', 'label' => 'Jugar', 'icon' => '⚽', 'params' => $gid] : ['route' => 'game.calendar', 'label' => 'Jugar', 'icon' => '⚽', 'params' => $gid],
                ['route' => 'game.squad', 'label' => 'Vestuario', 'icon' => '👥', 'params' => $gid],
                ['route' => 'game.menu', 'label' => 'Menú', 'icon' => '🧭', 'params' => $gid],
                ['route' => 'game.internet', 'label' => 'Internet', 'icon' => '🌐', 'params' => $gid],
                ['route' => 'game.calendar', 'label' => __('app.calendar'), 'icon' => '📅', 'params' => $gid],
            ])
            : [
                ['route' => 'dashboard', 'label' => __('app.my_games'), 'icon' => '🗂️', 'params' => []],
                ['route' => 'select-team', 'label' => __('app.new_game'), 'icon' => '➕', 'params' => []],
                ['route' => 'leaderboard', 'label' => __('leaderboard.title'), 'icon' => '🏆', 'params' => []],
                ['route' => 'friends.index', 'label' => __('friends.title'), 'icon' => '👥', 'params' => []],
            ];
    @endphp

    {{-- Desktop sidebar --}}
    <aside class="hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 lg:left-0 lg:w-72 lg:z-40 bg-surface-800 border-r border-border-default">
        <div class="h-1.5 bg-gradient-to-r from-purple-600 via-purple-500 to-accent-blue shrink-0"></div>
        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="flex items-center gap-2.5 px-5 h-16 shrink-0 border-b border-border-default">
            <span class="w-9 h-9 rounded-xl bg-purple-600 flex items-center justify-center shadow-lg shadow-purple-600/30 shrink-0">
                <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.2l1.7 3.5 3.8.5-2.8 2.7.7 3.8-3.4-1.8-3.4 1.8.7-3.8-2.8-2.7 3.8-.5z"/></svg>
            </span>
            <span class="font-heading font-bold text-lg uppercase tracking-wide text-text-primary">SheManager<span class="text-purple-400">Game</span></span>
        </a>

        @if($isGame)
            <a href="{{ route('show-game', $game->id) }}" class="flex items-center gap-3 px-5 py-4 border-b border-border-default hover:bg-surface-700/50 transition-colors">
                @if($game->team)
                    <x-team-crest :team="$game->team" class="w-11 h-11 shrink-0" />
                @endif
                <span class="min-w-0">
                    <span class="block font-heading font-semibold text-sm uppercase tracking-wide text-text-primary truncate">{{ $game->team?->name ?? __('app.my_team') }}</span>
                    <span class="block text-[11px] text-purple-400 font-semibold uppercase tracking-widest mt-0.5">Menú principal</span>
                </span>
            </a>
        @endif

        @if($isGame && $game->next_match)
            <a href="{{ route('game.lineup', $game->id) }}" class="mx-3 mt-4 mb-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold bg-gradient-to-r from-purple-600 to-accent-blue text-white shadow-lg shadow-purple-600/30 hover:opacity-90 transition-opacity shrink-0">
                ▶ Jugar el próximo partido
            </a>
        @endif

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5">
            @foreach($sections as $section)
                <div>
                    <p class="px-3 pb-1.5 text-[11px] font-bold uppercase tracking-widest text-purple-400">{{ $section[0] }}</p>
                    <div class="space-y-0.5">
                        @foreach($section[1] as $item)
                            @php $active = $currentRoute === $item['route']; @endphp
                            <a href="{{ route($item['route'], $item['params']) }}"
                               class="relative block px-3 py-2 rounded-lg text-sm transition-colors {{ $active ? 'bg-gradient-to-r from-purple-600/30 to-transparent text-text-primary font-bold' : 'text-text-body hover:bg-surface-700' }}">
                                @if($active)<span class="absolute left-0 top-1 bottom-1 w-1 rounded-full bg-purple-500"></span>@endif
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <p class="px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-text-faint">SheManagerGame</p>
                <div class="space-y-0.5">
                    @foreach($globalItems as $item)
                        @php $active = $currentRoute === $item['route']; @endphp
                        <a href="{{ route($item['route'], $item['params']) }}"
                           class="block px-3 py-2 rounded-lg text-sm transition-colors {{ $active ? 'bg-purple-600/20 text-text-primary font-semibold border border-purple-500/30' : 'text-text-body hover:bg-surface-700' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-sm text-accent-red hover:bg-surface-700 transition-colors">{{ __('app.log_out') }}</button>
                        </form>
                    @endauth
                </div>
            </div>
        </nav>
        <p class="px-5 py-3 text-[11px] text-text-faint border-t border-border-default shrink-0">&copy; {{ date('Y') }} Izan Delgado · Datos: Soccerdonna</p>
    </aside>

    {{-- Main column --}}
    <div class="flex-1 min-w-0 lg:ml-72 flex flex-col min-h-screen">
        {{-- Mobile top bar --}}
        <header class="lg:hidden sticky top-0 z-40 bg-surface-900/95 backdrop-blur-md border-b border-border-default">
            <div class="flex items-center justify-between px-4 h-14">
                <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-purple-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.2l1.7 3.5 3.8.5-2.8 2.7.7 3.8-3.4-1.8-3.4 1.8.7-3.8-2.8-2.7 3.8-.5z"/></svg>
                    </span>
                    <span class="font-heading font-bold text-base uppercase tracking-wide text-text-primary">SheManager<span class="text-purple-400">Game</span></span>
                </a>
                @if($isGame)
                    <a href="{{ route('game.menu', $game->id) }}" class="text-xs font-semibold uppercase tracking-wide text-purple-400 px-3 py-2 rounded-lg hover:bg-surface-700">🧭 Menú</a>
                @else
                    <a href="{{ route('dashboard') }}" class="text-xs font-semibold uppercase tracking-wide text-purple-400 px-3 py-2 rounded-lg hover:bg-surface-700">{{ __('app.my_games') }}</a>
                @endif
            </div>
            @if($isGame)
                <div class="flex items-center gap-3 px-4 pb-3">
                    @if($game->team)
                        <x-team-crest :team="$game->team" class="w-8 h-8 shrink-0" />
                    @endif
                    <span class="font-heading font-semibold text-sm uppercase tracking-wide text-text-primary truncate">{{ $game->team?->name ?? __('app.my_team') }}</span>
                </div>
            @endif
        </header>

        @isset($header)
            <div class="max-w-7xl w-full mx-auto px-4 pt-4 lg:pt-6">
                {{ $header }}
            </div>
        @endisset

        <main class="text-text-body flex-1 pb-28 lg:pb-10">
            {{ $slot }}
        </main>

        <footer class="hidden lg:block border-t border-border-default/50 mt-auto">
            <div class="max-w-7xl mx-auto px-4 py-6 flex flex-wrap items-center justify-between gap-3 text-xs text-text-faint">
                <span>SheManager<span class="text-purple-400">Game</span> · &copy; {{ date('Y') }} Izan Delgado</span>
                <span><a href="{{ route('legal') }}" class="hover:text-text-muted">{{ __('app.legal_notice') }}</a> · <a href="https://github.com/izandhh15/SheManagerGame" target="_blank" rel="noopener" class="hover:text-text-muted">GitHub</a></span>
            </div>
        </footer>
    </div>

    {{-- Mobile bottom bar --}}
    <nav class="lg:hidden fixed inset-x-0 bottom-0 z-40 bg-surface-900/95 backdrop-blur-md border-t border-border-default" style="padding-bottom: env(safe-area-inset-bottom, 0px);">
        <div class="flex items-stretch h-16">
            @foreach($mobileItems as $item)
                @php $active = $currentRoute === $item['route']; @endphp
                <a href="{{ route($item['route'], $item['params']) }}"
                   class="flex-1 flex flex-col items-center justify-center gap-0.5 min-h-[44px] px-1 transition-colors {{ $active ? 'text-purple-400' : 'text-text-muted' }}">
                    <span class="text-lg leading-none">{{ $item['icon'] }}</span>
                    <span class="text-[10px] font-medium uppercase tracking-wider leading-tight text-center">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>
</div>
</body>
</html>
