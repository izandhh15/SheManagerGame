@props(['game', 'nextMatch' => null, 'teamCompetitions' => collect()])

@php
    $currentRoute = Route::currentRouteName();
    $isCareer = $game->isCareerMode();
    $isTournament = $game->isTournamentMode();

    // Active states — mirror the desktop nav groups (Mi equipo, Fichajes, Club,
    // Competición, Selección) so the right tab lights up on every page.
    $dashboardActive = $currentRoute === 'show-game';
    $squadActive = in_array($currentRoute, ['game.squad', 'game.player.detail', 'game.squad.academy', 'game.academy.detail']);
    $lineupActive = $currentRoute === 'game.lineup';
    $transfersActive = in_array($currentRoute, ['game.transfers', 'game.transfers.outgoing', 'game.scouting', 'game.scouting.results', 'game.explore', 'game.explore.teams', 'game.explore.squad', 'game.explore.pool-teams', 'game.explore.team', 'game.transfer-activity', 'game.transfers.market']);
    $clubRoutes = ['game.club', 'game.club.finances', 'game.club.investment', 'game.club.stadium', 'game.club.commercial', 'game.club.reputation'];
    $moreActive = in_array($currentRoute, array_merge($clubRoutes, [
        'game.calendar', 'game.competition', 'game.results', 'game.manager.career',
        'game.squad.planner', 'game.squad.registration', 'game.squad.reserve', 'game.opponent-analysis',
        'game.national-squad-picker', 'game.match.summary', 'game.live-match',
    ]));

@endphp

<div x-data="{ moreOpen: false }" class="lg:hidden">
    {{-- Bottom Tab Bar --}}
    <nav class="fixed inset-x-0 z-40 bg-surface-900/95 backdrop-blur-md border-t border-border-default" style="bottom: calc(env(safe-area-inset-bottom, 0px) + 0.5rem);">
        <div class="flex items-center h-20">
            {{-- Dashboard --}}
            <a href="{{ route('show-game', $game->id) }}" class="flex-1 flex flex-col items-center justify-center gap-1 min-h-[44px] px-3 transition-colors {{ $dashboardActive ? 'text-accent-blue' : 'text-text-muted' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                </svg>
                <span class="text-[10px] font-medium uppercase tracking-wider leading-tight text-center">{{ __('app.dashboard') }}</span>
            </a>

            {{-- Squad --}}
            <a href="{{ route('game.squad', $game->id) }}" class="flex-1 flex flex-col items-center justify-center gap-1 min-h-[44px] px-3 transition-colors {{ $squadActive ? 'text-accent-blue' : 'text-text-muted' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
                <span class="text-[10px] font-medium uppercase tracking-wider leading-tight text-center">{{ __('app.squad') }}</span>
            </a>

            {{-- Starting XI (only when there's a next match) --}}
            @if($nextMatch)
            <a href="{{ route('game.lineup', $game->id) }}" class="flex-1 flex flex-col items-center justify-center gap-1 min-h-[44px] px-3 transition-colors {{ $lineupActive ? 'text-accent-blue' : 'text-text-muted' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/>
                </svg>
                <span class="text-[10px] font-medium uppercase tracking-wider leading-tight text-center">{{ __('app.starting_xi') }}</span>
            </a>
            @endif

            {{-- Transfers (career mode only) --}}
            @if($isCareer)
            <a href="{{ route('game.transfers', $game->id) }}" class="flex-1 flex flex-col items-center justify-center gap-1 min-h-[44px] px-3 transition-colors {{ $transfersActive ? 'text-accent-blue' : 'text-text-muted' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                </svg>
                <span class="text-[10px] font-medium uppercase tracking-wider leading-tight text-center">{{ __('app.transfers') }}</span>
            </a>
            @endif

            {{-- More --}}
            <button
                @click="moreOpen = !moreOpen"
                class="relative flex-1 flex flex-col items-center justify-center gap-1 min-h-[44px] px-3 transition-colors {{ $moreActive ? 'text-accent-blue' : 'text-text-muted' }}"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                </svg>
                <span class="text-[10px] font-medium uppercase tracking-wider leading-tight text-center">{{ __('app.more') }}</span>
            </button>
        </div>
    </nav>

    {{-- More Menu Overlay --}}
    <div x-show="moreOpen" x-cloak class="fixed inset-0 z-50">
        {{-- Backdrop --}}
        <div
            x-show="moreOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="moreOpen = false"
            class="fixed inset-0 bg-black/60"
        ></div>

        {{-- Slide-up Panel --}}
        <div
            x-show="moreOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="fixed bottom-0 inset-x-0 max-h-[85vh] bg-surface-800 border-t border-border-strong rounded-t-2xl shadow-2xl overflow-y-auto"
            style="padding-bottom: env(safe-area-inset-bottom, 0px);"
        >
            {{-- Drag handle --}}
            <div class="flex justify-center pt-3 pb-2">
                <div class="w-10 h-1 rounded-full bg-surface-600"></div>
            </div>

            {{-- Menu Items --}}
            <nav class="px-2 pb-4 space-y-1">
                @php
                    $isNationalTeam = $game->team->type === 'national';
                    $moreItem = 'flex items-center gap-3 px-4 py-4 rounded-xl transition-colors ';
                    $moreItemActive = 'bg-accent-blue/10 text-accent-blue';
                    $moreItemIdle = 'text-text-body hover:bg-surface-700';
                @endphp

                {{-- 📋 MI EQUIPO --}}
                <div class="pt-2 pb-1 px-4">
                    <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('app.my_team') }}</span>
                </div>
                @if($nextMatch)
                <a href="{{ route('game.opponent-analysis', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.opponent-analysis' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('app.scout_opponent') }}</span>
                </a>
                @endif
                @if($isCareer)
                @if(!$game->isFilial())
                <a href="{{ route('game.squad.academy', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ in_array($currentRoute, ['game.squad.academy', 'game.academy.detail']) ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.26 10.147a60.436 60.436 0 0 0-.491 6.347A48.627 48.627 0 0 1 12 20.904a48.627 48.627 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.57 50.57 0 0 0-2.658-.813A59.905 59.905 0 0 1 12 3.493a59.902 59.902 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/>
                    </svg>
                    <span class="text-sm font-medium">🌱 {{ __('squad.academy') }}</span>
                </a>
                @endif
                <a href="{{ route('game.squad.planner', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.squad.planner' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('app.tactics') }}</span>
                </a>
                <a href="{{ route('game.squad.registration', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.squad.registration' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('squad.registration') }}</span>
                </a>
                @if($game->isFilial())
                <a href="{{ route('game.squad.reserve', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.squad.reserve' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('squad.reserve_team') }}</span>
                </a>
                @endif
                @endif

                {{-- 🔄 FICHAJES (career only) --}}
                @if($isCareer)
                <div class="pt-2 pb-1 px-4">
                    <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('app.transfers') }}</span>
                </div>
                <a href="{{ route('game.transfers.market', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.transfers.market' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72L4.318 3.44A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72m-13.5 8.615c0 .331.27.6.6.6h3.6a.6.6 0 0 0 .6-.6v-1.2a.6.6 0 0 0-.6-.6h-3.6a.6.6 0 0 0-.6.6v1.2Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('transfers.market_tab') }}</span>
                </a>
                <a href="{{ route('game.transfers.outgoing', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.transfers.outgoing' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('transfers.outgoing') }}</span>
                </a>
                <a href="{{ route('game.scouting', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ in_array($currentRoute, ['game.scouting', 'game.scouting.results']) ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('transfers.scouting_tab') }}</span>
                </a>
                <a href="{{ route('game.explore', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ Str::startsWith($currentRoute, 'game.explore') ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5a11.964 11.964 0 0 1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-2.632 0-5.152-.577-7.416-1.626m0 0A12.04 12.04 0 0 1 3 12c0-.778.099-1.533.284-2.253"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('transfers.explore_tab') }}</span>
                </a>
                @endif
                {{-- (Cantera movida a MI EQUIPO, más visible) --}}

                {{-- 🏟️ CLUB (career only) --}}
                @if($isCareer)
                <div class="pt-2 pb-1 px-4">
                    <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('app.club') }}</span>
                </div>
                <a href="{{ route('game.club.stadium', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.club.stadium' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('club.nav.stadium') }}</span>
                </a>
                <a href="{{ route('game.club.finances', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ in_array($currentRoute, ['game.club', 'game.club.finances']) ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('club.nav.finances') }}</span>
                </a>
                <a href="{{ route('game.club.investment', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.club.investment' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('club.nav.investment') }}</span>
                </a>
                <a href="{{ route('game.club.commercial', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.club.commercial' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('club.nav.commercial') }}</span>
                </a>
                <a href="{{ route('game.club.reputation', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.club.reputation' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18.75 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L22.5 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('club.nav.reputation') }}</span>
                </a>
                @endif

                {{-- 📅 COMPETICIÓN --}}
                <div class="pt-2 pb-1 px-4">
                    <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('app.competitions') }}</span>
                </div>
                <a href="{{ route('game.calendar', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.calendar' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('app.calendar') }}</span>
                </a>
                <a href="{{ route('game.calendar', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('app.results') }}</span>
                </a>
                @if($teamCompetitions->isNotEmpty())
                <div class="pt-2 pb-1 px-4">
                    <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('game.standings') }}</span>
                </div>
                @foreach($teamCompetitions as $competition)
                <a href="{{ route('game.competition', [$game->id, $competition->id]) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ request()->route('competitionId') == $competition->id ? $moreItemActive : $moreItemIdle }}">
                    <x-competition-logo :competition="$competition" class="w-7 h-7 shrink-0" />
                    <span class="text-sm font-medium">{{ __($competition->name) }}</span>
                </a>
                @endforeach
                @endif

                {{-- 🇪🇸 SELECCIÓN (national teams only) --}}
                @if($isNationalTeam)
                <div class="pt-2 pb-1 px-4">
                    <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('app.selection') }}</span>
                </div>
                <a href="{{ route('game.national-squad-picker', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.national-squad-picker' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('game.convocatoria') }}</span>
                </a>
                <a href="{{ route('game.calendar', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 0 1 0 1.971l-11.54 6.347a1.125 1.125 0 0 1-1.667-.985V5.653Z"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('app.matches') }}</span>
                </a>
                @endif

                {{-- Mi carrera (pro-manager only) --}}
                @if($game->isProManagerMode())
                <a href="{{ route('game.manager.career', $game->id) }}" @click="moreOpen = false" class="{{ $moreItem }}{{ $currentRoute === 'game.manager.career' ? $moreItemActive : $moreItemIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/>
                    </svg>
                    <span class="text-sm font-medium">{{ __('manager.career_title') }}</span>
                </a>
                @endif

                {{-- Site Links --}}
                @if(auth()->user())
                <div class="border-t border-border-default/50 mt-2 pt-3">
                    <div class="pb-1 px-4">
                        <span class="text-[10px] font-semibold text-text-muted uppercase tracking-widest">{{ __('app.account') }}</span>
                    </div>
                    <a href="{{ route('select-team') }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors text-text-body hover:bg-surface-700">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <span class="text-sm font-medium">{{ __('app.new_game') }}</span>
                    </a>
                    <a href="{{ route('dashboard') }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors text-text-body hover:bg-surface-700">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776"/>
                        </svg>
                        <span class="text-sm font-medium">{{ __('app.load_game') }}</span>
                    </a>
                    <a href="{{ route('leaderboard') }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors text-text-body hover:bg-surface-700">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                        </svg>
                        <span class="text-sm font-medium">{{ __('leaderboard.title') }}</span>
                    </a>
                    <a href="{{ route('profile.edit') }}" @click="moreOpen = false" class="flex items-center gap-3 px-4 py-4 rounded-xl transition-colors text-text-body hover:bg-surface-700">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                        <span class="text-sm font-medium">{{ __('app.edit_profile') }}</span>
                    </a>
                    <div class="flex items-center justify-between px-4 py-4">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 shrink-0 text-text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                            </svg>
                            <span class="text-sm font-medium text-text-body">{{ __('app.theme') }}</span>
                        </div>
                        <x-theme-toggle />
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" @click="moreOpen = false" class="flex items-center gap-3 w-full px-4 py-4 rounded-xl transition-colors text-text-body hover:bg-surface-700">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                            </svg>
                            <span class="text-sm font-medium">{{ __('app.log_out') }}</span>
                        </button>
                    </form>
                </div>
                @endif

                {{-- Copyright --}}
                <div class="border-t border-border-default/50 mt-2 pt-3 px-4 pb-2">
                    <p class="text-[10px] text-text-faint text-center">
                        &copy; {{ date('Y') }} Izan Delgado &middot; <a href="{{ route('legal') }}" class="hover:text-text-muted transition-colors">{{ __('app.legal_notice') }}</a> &middot; <a href="https://instagram.com/shemanagergame" target="_blank" rel="noopener" class="hover:text-text-muted transition-colors">Instagram</a>
                        @if(auth()->user()?->is_admin)
                            &middot; <a href="{{ route('admin.dashboard') }}" class="hover:text-text-muted transition-colors">Admin</a>
                        @endif
                    </p>
                </div>

            </nav>
        </div>
    </div>
</div>
