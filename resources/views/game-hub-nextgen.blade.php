{{--
    Next-gen game hub — REDISEÑO 25-10-2026 (petición de Izan: "los menús
    siguen siendo los mismos").

    Solo cambia el menú: ninguna carta cambia datos ni lógica, todas
    enlazan a rutas EXISTENTES. Agrupación, orden y nombres nuevos, en
    línea con la barra lateral del mismo rediseño, y cartas que el menú
    viejo no tenía (Rival, Día de partido, Actividad).
--}}
@php /** @var App\Models\Game $game **/ @endphp

<x-game-nextgen-layout :game="$game">
    <div class="max-w-7xl mx-auto px-4 pb-8">
        {{-- Hero --}}
        <div class="mt-6 rounded-2xl border border-purple-500/30 bg-gradient-to-br from-purple-600/20 via-surface-800 to-surface-800 p-5 sm:p-6">
            <div class="flex flex-wrap items-center gap-4">
                @if($game->team)
                    <x-team-crest :team="$game->team" class="w-16 h-16 sm:w-20 sm:h-20 shrink-0" />
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-purple-400">Menú principal</p>
                    <h1 class="font-heading font-bold text-2xl sm:text-3xl uppercase tracking-wide text-text-primary truncate">{{ $game->team?->name ?? __('app.my_team') }}</h1>
                    @if($nextMatch)
                        <p class="text-sm text-text-secondary mt-1">
                            Próximo partido: {{ $nextMatch->homeTeam?->name ?? '' }} vs {{ $nextMatch->awayTeam?->name ?? '' }}
                            @if($nextMatch->scheduled_date)
                                · {{ $nextMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                            @endif
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    @if($nextMatch)
                        <a href="{{ route('game.lineup', $game->id) }}" class="px-4 py-2.5 rounded-xl text-sm font-bold bg-gradient-to-r from-purple-600 to-accent-blue text-white hover:opacity-90 shadow-lg shadow-purple-600/30">▶ Jugar el próximo partido</a>
                    @endif
                    <a href="{{ route('show-game', $game->id) }}" class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-border-strong text-text-body hover:bg-surface-700">🏠 Panel</a>
                </div>
            </div>
        </div>

        @php
            $isCareer = $game->isCareerMode();
            $isNational = $game->team?->type === 'national';
            $isPro = $game->isProManagerMode();
            $id = $game->id;

            $card = 'block rounded-2xl bg-surface-800 border border-border-default p-5 hover:border-purple-500/50 transition-colors';
            $cardTitle = 'font-heading font-bold text-base uppercase tracking-wide text-text-primary';
            $cardDesc = 'mt-1 text-sm text-text-secondary leading-relaxed';

            $sections = [];

            $sections[] = ['⚽ El partido', [
                ['route' => 'game.calendar', 'title' => '📅 ' . __('app.calendar'), 'desc' => 'Todos los partidos, jornadas y resultados de la temporada.'],
                ['route' => 'game.lineup', 'title' => '⚽ ' . __('app.starting_xi'), 'desc' => 'El once, la táctica y el banquillo antes del partido.'],
                ['route' => 'game.opponent-analysis', 'title' => '🔍 Rival', 'desc' => 'Análisis del próximo rival: forma, estilo y jugadoras clave.'],
            ]];

            $vestuario = [
                ['route' => 'game.squad', 'title' => '👥 ' . __('app.squad'), 'desc' => 'La plantilla: jugadoras, estados, roles y dorsales.'],
            ];
            if ($isCareer) {
                $vestuario[] = ['route' => 'game.squad.planner', 'title' => '📋 ' . __('app.tactics'), 'desc' => 'Planifica la plantilla y la táctica a largo plazo.'];
                if (! $game->isFilial()) {
                    $vestuario[] = ['route' => 'game.squad.academy', 'title' => '🌱 ' . __('squad.academy'), 'desc' => 'La cantera: promesas, crecimiento y promociones.'];
                }
                $vestuario[] = ['route' => 'game.squad.registration', 'title' => '📝 ' . __('squad.registration'), 'desc' => 'Inscripciones y límites de plantilla por competición.'];
                if ($game->isFilial()) {
                    $vestuario[] = ['route' => 'game.squad.reserve', 'title' => '👥 ' . __('squad.reserve_team'), 'desc' => 'El filial: subidas y bajadas con el primer equipo.'];
                }
            }
            $sections[] = ['👥 Vestuario', $vestuario];

            if ($isCareer) {
                $sections[] = ['🔄 Mercado', [
                    ['route' => 'game.transfers.market', 'title' => '🛒 ' . __('transfers.market_tab'), 'desc' => 'El mercado: fichar, vender y seguir objetivos.'],
                    ['route' => 'game.transfers', 'title' => '⬇️ ' . __('transfers.incoming'), 'desc' => 'Ofertas recibidas y negociaciones en curso.'],
                    ['route' => 'game.transfers.outgoing', 'title' => '⬆️ ' . __('transfers.outgoing'), 'desc' => 'Bajas, cesiones y jugadoras en venta.'],
                    ['route' => 'game.transfer-activity', 'title' => '📰 Actividad', 'desc' => 'Movimientos recientes del mercado.'],
                    ['route' => 'game.scouting', 'title' => '🔭 ' . __('transfers.scouting_tab'), 'desc' => 'Ojeadores, informes y jugadoras seguidas.'],
                    ['route' => 'game.explore', 'title' => '🌍 ' . __('transfers.explore_tab'), 'desc' => 'Explora otros equipos, ligas y plantillas.'],
                ]];
                $sections[] = ['🏟️ El club', [
                    ['route' => 'game.club.finances', 'title' => '💰 ' . __('club.nav.finances'), 'desc' => 'Cuentas, presupuesto, ingresos y gastos.'],
                    ['route' => 'game.club.stadium', 'title' => '🏟️ ' . __('club.nav.stadium'), 'desc' => 'El estadio: gradas, abonos y mejoras.'],
                    ['route' => 'game.club.matchday', 'title' => '🎟️ Día de partido', 'desc' => 'Entradas, ambiente y organización del día de partido.'],
                    ['route' => 'game.club.commercial', 'title' => '🤝 ' . __('club.nav.commercial'), 'desc' => 'Patrocinadores y derechos de nombre.'],
                    ['route' => 'game.club.investment', 'title' => '📈 ' . __('club.nav.investment'), 'desc' => 'Inversiones e infraestructuras del club.'],
                    ['route' => 'game.club.reputation', 'title' => '⭐ ' . __('club.nav.reputation'), 'desc' => 'La reputación del club y su proyección.'],
                ]];
            }

            $social = [
                ['route' => 'game.internet', 'title' => '🌐 ' . __('game.internet_title'), 'desc' => 'El feed: prensa, fichajes y tu voz de míster (tuit libre, 200 caracteres).'],
                ['route' => 'game.social', 'title' => '🐦 ' . __('game.nav_social'), 'desc' => 'La red social del juego: reacciones de la afición y la prensa.'],
                ['route' => 'game.club-social', 'title' => '📢 ' . __('game.nav_club_social'), 'desc' => 'Los anuncios oficiales de tu club.'],
            ];
            if ($isNational) {
                $social[] = ['route' => 'game.national-social', 'title' => '📢 ' . __('game.nav_national_social'), 'desc' => 'Los anuncios oficiales de tu selección.'];
            }
            $sections[] = ['📣 Grada y prensa', $social];

            if ($isNational) {
                $sections[] = ['🇪🇸 ' . __('app.selection'), [
                    ['route' => 'game.national-squad-picker', 'title' => __('game.convocatoria'), 'desc' => 'La convocatoria: elige a las 23 (o las que toquen) para la ventana.'],
                    ['route' => 'game.national-venues', 'title' => '🏟️ Sedes', 'desc' => 'Dónde juega en casa la selección: sedes y peticiones.'],
                ]];
            }

            if ($isPro) {
                $sections[] = ['💼 Carrera', [
                    ['route' => 'game.manager.career', 'title' => __('manager.career_title'), 'desc' => 'Tu historial como entrenador, temporada a temporada.'],
                    ['route' => 'game.job-market', 'title' => '💼 Mercado de trabajo', 'desc' => 'Ofertas de otros clubes y selecciones.'],
                ]];
            }

            // Sin próximo partido, la alineación y el análisis del rival no
            // tienen sentido, así que esas cartas se quitan en vez de
            // llevar a un error.
            if (! $nextMatch) {
                foreach ($sections as $si => $section) {
                    $sections[$si][1] = array_values(array_filter($section[1], fn ($c) => ! in_array($c['route'], ['game.lineup', 'game.opponent-analysis'], true)));
                }
            }
        @endphp

        @foreach($sections as $section)
            <h2 class="font-heading text-xl font-bold uppercase tracking-wide text-text-primary mt-10 mb-4">{{ $section[0] }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($section[1] as $item)
                    <a href="{{ route($item['route'], $id) }}" class="{{ $card }}">
                        <p class="{{ $cardTitle }}">{{ $item['title'] }}</p>
                        <p class="{{ $cardDesc }}">{{ $item['desc'] }}</p>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-purple-400">Entrar →</p>
                    </a>
                @endforeach
            </div>
        @endforeach

        <p class="mt-10 text-sm text-text-secondary">¿Prefieres el panel de siempre? <a href="{{ route('show-game', $game->id) }}" class="text-purple-400 hover:underline">Ir al panel</a>.</p>
    </div>
</x-game-nextgen-layout>
