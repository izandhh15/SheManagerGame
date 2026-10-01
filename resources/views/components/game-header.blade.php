@props(['game', 'nextMatch' => null, 'continueToHome' => false])

@php
    // Get competitions the team participates in for this game
    $teamCompetitions = \App\Models\Competition::whereIn('id',
        $game->competitionEntries()
            ->where('team_id', $game->team_id)
            ->pluck('competition_id')
    )->orderBy('tier')->get();

    // Notifications for mobile bell icon + modal. The modal mirrors the
    // dashboard inbox: the current matchday's (unread) notifications only, since
    // markAllAsRead on each advance clears the previous matchday's.
    $unreadCount = $game->notifications()->whereNull('read_at')->count();
    $recentNotifications = $game->notifications()->unread()->orderByDesc('game_date')->limit(20)->get();

    // Highest-stakes (CRITICAL) notifications that haven't been acknowledged yet
    // surface as a blocking, must-dismiss popup on the next page load so they
    // can't be missed (e.g. a purchase offer for one of your players). All pending
    // criticals of the most-recent type are shown together as one group (single
    // dismiss + single action, since they route to the same page); other types
    // follow as their own group on subsequent loads.
    $criticalAlerts = app(\App\Modules\Notification\Services\NotificationService::class)
        ->pendingCriticalAlertGroup($game->id);
@endphp

<div x-data>
    {{-- Sticky Header --}}
    <header class="sticky top-0 z-50 bg-surface-900/95 backdrop-blur-md border-b border-border-default">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between pt-0 py-2">
                {{-- Left: Team badge + name (links to dashboard) --}}
                <div class="flex items-center gap-3">
                    <a href="{{ route('show-game', $game->id) }}" class="flex items-center gap-2.5 rounded-md -mx-1 px-1 py-0.5 hover:bg-surface-700/50 transition-colors" aria-label="{{ __('app.dashboard') }}">
                        <x-team-crest :team="$game->team" class="w-8 h-8 shrink-0" />
                        <div class="min-w-0">
                            <h1 class="font-heading font-semibold text-base text-text-primary leading-none tracking-wide uppercase truncate">{{ $game->team->name }}</h1>
                            <p class="text-[10px] text-text-muted tracking-widest mt-0.5">
                                @if($game->isCareerMode() && $game->current_date)
                                    <span class="inline-flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3">
                                            <path fill-rule="evenodd" d="M4 1.75a.75.75 0 0 1 1.5 0V3h5V1.75a.75.75 0 0 1 1.5 0V3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2V1.75ZM4.5 6a1 1 0 0 0-1 1v4.5a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-7Z" clip-rule="evenodd" />
                                        </svg>
                                        {{ Str::upper($game->current_date->locale(app()->getLocale())->translatedFormat('j F Y')) }}
                                    </span>
                                @elseif($game->isTournamentMode())
                                    <span class="uppercase">{{ __($teamCompetitions[0]->name ?? '') }}</span>
                                @endif
                            </p>
                        </div>
                    </a>
                </div>

                {{-- Center: Desktop nav (redesigned: icon + label, grouped dropdowns) --}}
                <nav class="hidden lg:flex items-center gap-1">
                    @if($game->isCareerMode())
                    @php
                        $squadActive = Str::startsWith(Route::currentRouteName(), 'game.squad');
                        $squadSecondary = $game->isFilial()
                            ? ['route' => 'game.squad.reserve', 'label' => __('squad.reserve_team'), 'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Z']
                            : ['route' => 'game.squad.academy', 'label' => __('squad.academy'), 'icon' => 'M4.26 10.147a60.436 60.436 0 0 0-.491 6.347A48.627 48.627 0 0 1 12 20.904a48.627 48.627 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.57 50.57 0 0 0-2.658-.813A59.905 59.905 0 0 1 12 3.493a59.902 59.902 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5'];
                        $squadItems = [
                            ['route' => 'game.squad', 'label' => __('squad.first_team'), 'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
                            ['route' => 'game.squad.planner', 'label' => __('planner.planner'), 'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'],
                            ['route' => $squadSecondary['route'], 'label' => $squadSecondary['label'], 'icon' => $squadSecondary['icon']],
                            ['route' => 'game.squad.registration', 'label' => __('squad.registration'), 'icon' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                        ];
                    @endphp
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ $squadActive ? 'bg-surface-700/70 text-text-primary' : 'text-text-muted hover:text-text-primary hover:bg-surface-700/40' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                            {{ __('app.squad') }}
                            <svg class="w-3 h-3 opacity-60 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 z-50 mt-2 w-56 rounded-xl shadow-xl bg-surface-800 border border-border-strong overflow-hidden">
                            <div class="py-1.5">
                                @foreach($squadItems as $item)
                                <a href="{{ route($item['route'], $game->id) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm transition-colors {{ Route::currentRouteName() == $item['route'] ? 'bg-purple-600/15 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700' }}">
                                    <svg class="w-4.5 h-4.5 w-[18px] h-[18px] shrink-0 {{ Route::currentRouteName() == $item['route'] ? 'text-purple-400' : 'text-text-muted' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $item['icon'] }}"/></svg>
                                    {{ $item['label'] }}
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @else
                    <a href="{{ route('game.squad', $game->id) }}" class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ Str::startsWith(Route::currentRouteName(), 'game.squad') ? 'bg-surface-700/70 text-text-primary' : 'text-text-muted hover:text-text-primary hover:bg-surface-700/40' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                        {{ __('app.squad') }}
                    </a>
                    @if($game->team->type === 'national')
                    <a href="{{ route('game.national-squad-picker', $game->id) }}" class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ Route::currentRouteName() == 'game.national-squad-picker' ? 'bg-surface-700/70 text-text-primary' : 'text-text-muted hover:text-text-primary hover:bg-surface-700/40' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25"/></svg>
                        {{ __('game.convocatoria') }}
                    </a>
                    @endif
                    @endif
                    @if($nextMatch)
                    <a href="{{ route('game.lineup', $game->id) }}" class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ Route::currentRouteName() == 'game.lineup' ? 'bg-surface-700/70 text-text-primary' : 'text-text-muted hover:text-text-primary hover:bg-surface-700/40' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>
                        {{ __('app.starting_xi') }}
                    </a>
                    @endif
                    @if($game->isCareerMode())
                    @php
                        $clubRoutes = ['game.club', 'game.club.finances', 'game.club.investment', 'game.club.stadium', 'game.club.commercial', 'game.club.reputation'];
                        $clubActive = in_array(Route::currentRouteName(), $clubRoutes);
                        $clubItems = [
                            ['route' => 'game.club.finances', 'label' => __('club.nav.finances'), 'icon' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z'],
                            ['route' => 'game.club.investment', 'label' => __('club.nav.investment'), 'icon' => 'M2.25 18 13.5 6.75a1.5 1.5 0 0 1 2.122 0l3.628 3.628a1.5 1.5 0 0 1 0 2.122L6.75 21H3v-3.75L18.75 2.25M2.25 18l3.628-3.628'],
                            ['route' => 'game.club.stadium', 'label' => __('club.nav.stadium'), 'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'],
                            ['route' => 'game.club.commercial', 'label' => __('club.nav.commercial'), 'icon' => 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z'],
                            ['route' => 'game.club.reputation', 'label' => __('club.nav.reputation'), 'icon' => 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18.75 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L22.5 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z'],
                        ];
                    @endphp
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ $clubActive ? 'bg-surface-700/70 text-text-primary' : 'text-text-muted hover:text-text-primary hover:bg-surface-700/40' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                            {{ __('app.club') }}
                            <svg class="w-3 h-3 opacity-60 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 z-50 mt-2 w-56 rounded-xl shadow-xl bg-surface-800 border border-border-strong overflow-hidden">
                            <div class="py-1.5">
                                @foreach($clubItems as $item)
                                <a href="{{ route($item['route'], $game->id) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm transition-colors {{ Route::currentRouteName() == $item['route'] ? 'bg-purple-600/15 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700' }}">
                                    <svg class="w-[18px] h-[18px] shrink-0 {{ Route::currentRouteName() == $item['route'] ? 'text-purple-400' : 'text-text-muted' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $item['icon'] }}"/></svg>
                                    {{ $item['label'] }}
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @php
                        $transfersRoutes = ['game.transfers', 'game.transfers.outgoing', 'game.scouting', 'game.scouting.results', 'game.explore', 'game.explore.teams', 'game.explore.squad', 'game.explore.pool-teams', 'game.transfers.market'];
                        $transfersActive = in_array(Route::currentRouteName(), $transfersRoutes);
                        $transferItems = [
                            ['route' => 'game.transfers', 'label' => __('transfers.incoming'), 'icon' => 'M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25', 'match' => ['game.transfers']],
                            ['route' => 'game.transfers.outgoing', 'label' => __('transfers.outgoing'), 'icon' => 'M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25', 'match' => ['game.transfers.outgoing']],
                            ['route' => 'game.scouting', 'label' => __('transfers.scouting_tab'), 'icon' => 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z', 'match' => ['game.scouting', 'game.scouting.results']],
                            ['route' => 'game.explore', 'label' => __('transfers.explore_tab'), 'icon' => 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5a11.964 11.964 0 0 1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-2.632 0-5.152-.577-7.416-1.626m0 0A12.04 12.04 0 0 1 3 12c0-.778.099-1.533.284-2.253', 'match' => ['game.explore', 'game.explore.teams', 'game.explore.squad', 'game.explore.pool-teams']],
                            ['route' => 'game.transfers.market', 'label' => __('transfers.market_tab'), 'icon' => 'M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72L4.318 3.44A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72m-13.5 8.615c0 .331.27.6.6.6h3.6a.6.6 0 0 0 .6-.6v-1.2a.6.6 0 0 0-.6-.6h-3.6a.6.6 0 0 0-.6.6v1.2Z', 'match' => ['game.transfers.market']],
                        ];
                    @endphp
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ $transfersActive ? 'bg-surface-700/70 text-text-primary' : 'text-text-muted hover:text-text-primary hover:bg-surface-700/40' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                            {{ __('app.transfers') }}
                            <svg class="w-3 h-3 opacity-60 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 z-50 mt-2 w-56 rounded-xl shadow-xl bg-surface-800 border border-border-strong overflow-hidden">
                            <div class="py-1.5">
                                @foreach($transferItems as $item)
                                @php $isActive = in_array(Route::currentRouteName(), $item['match']); @endphp
                                <a href="{{ route($item['route'], $game->id) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm transition-colors {{ $isActive ? 'bg-purple-600/15 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700' }}">
                                    <svg class="w-[18px] h-[18px] shrink-0 {{ $isActive ? 'text-purple-400' : 'text-text-muted' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $item['icon'] }}"/></svg>
                                    {{ $item['label'] }}
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    <a href="{{ route('game.calendar', $game->id) }}" class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ Route::currentRouteName() == 'game.calendar' ? 'bg-surface-700/70 text-text-primary' : 'text-text-muted hover:text-text-primary hover:bg-surface-700/40' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                        {{ __('app.calendar') }}
                    </a>
                    @if($game->isTournamentMode() && $teamCompetitions->isNotEmpty())
                    <a href="{{ route('game.competition', [$game->id, $teamCompetitions[0]->id]) }}" class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ Route::currentRouteName() == 'game.competition' ? 'bg-surface-700/70 text-text-primary' : 'text-text-muted hover:text-text-primary hover:bg-surface-700/40' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.228a6.003 6.003 0 0 1-5.415 5.002"/></svg>
                        {{ __('game.standings') }}
                    </a>
                    @else
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ Route::currentRouteName() == 'game.competition' ? 'bg-surface-700/70 text-text-primary' : 'text-text-muted hover:text-text-primary hover:bg-surface-700/40' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.228a6.003 6.003 0 0 1-5.415 5.002"/></svg>
                            {{ __('app.competitions') }}
                            <svg class="w-3 h-3 opacity-60 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 z-50 mt-2 w-60 rounded-xl shadow-xl bg-surface-800 border border-border-strong overflow-hidden">
                            <div class="py-1.5">
                                @foreach($teamCompetitions as $competition)
                                <a href="{{ route('game.competition', [$game->id, $competition->id]) }}" class="flex items-center gap-3 px-4 py-2.5 text-sm transition-colors {{ request()->route('competitionId') == $competition->id ? 'bg-purple-600/15 text-text-primary font-semibold' : 'text-text-body hover:bg-surface-700' }}">
                                    <x-competition-logo :competition="$competition" class="w-6 h-6 shrink-0 rounded" />
                                    <span class="truncate">{{ __($competition->name) }}</span>
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    @if($game->isProManagerMode())
                    <a href="{{ route('game.manager.career', $game->id) }}" class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors {{ Route::currentRouteName() == 'game.manager.career' ? 'bg-surface-700/70 text-text-primary' : 'text-text-muted hover:text-text-primary hover:bg-surface-700/40' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        {{ __('manager.career_title') }}
                    </a>
                    @endif
                </nav>

                {{-- Right: Notification bell + action button --}}
                <div class="flex items-center gap-2">
                    {{-- Mobile notification bell --}}
                    <button
                        @click="$dispatch('open-modal', 'notifications-mobile')"
                        class="lg:hidden relative inline-flex items-center justify-center p-2 min-h-[44px] min-w-[44px] rounded-sm text-text-secondary hover:text-text-primary hover:bg-surface-700 transition-colors shrink-0"
                        aria-label="{{ __('notifications.inbox') }}"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                        </svg>
                        @if($unreadCount > 0)
                        <span class="absolute top-1 right-1 min-w-[16px] h-4 px-0.5 rounded-full bg-accent-red text-white text-[8px] font-bold flex items-center justify-center">
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                        @endif
                    </button>

                    @if($nextMatch)
                        <div class="hidden sm:flex items-center gap-2 bg-surface-700/50 rounded-lg px-2.5 py-1">
                            <x-team-crest :team="$nextMatch->homeTeam" class="w-6 h-6 cursor-help" x-data x-tooltip.raw="{{ $nextMatch->homeTeam->name }}" />
                            <span class="text-xs font-semibold text-text-muted font-heading tracking-wide">vs</span>
                            <x-team-crest :team="$nextMatch->awayTeam" class="w-6 h-6 cursor-help" x-data x-tooltip.raw="{{ $nextMatch->awayTeam->name }}" />
                        </div>
                        @if($game->hasPendingActions())
                            @php $pendingAction = $game->getFirstPendingAction(); @endphp
                            <x-primary-button-link size="sm" color="amber" :href="$pendingAction && $pendingAction['route'] ? route($pendingAction['route'], $game->id) : route('show-game', $game->id)" class="whitespace-nowrap gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <span class="hidden sm:inline">{{ __('messages.action_required_short') }}</span>
                            </x-primary-button-link>
                        @elseif($continueToHome)
                            <x-primary-button-link size="sm" :href="route('show-game', $game->id)">{{ __('app.continue') }}</x-primary-button-link>
                        @elseif($game->isFastMode())
                            <a href="{{ route('game.fast-mode', $game->id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 min-h-[36px] text-xs font-semibold uppercase tracking-wider rounded-lg bg-accent-blue/10 text-accent-blue border border-accent-blue/30 hover:bg-accent-blue/20 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span>{{ __('game.fast_mode') }}</span>
                            </a>
                        @elseif($game->isTournamentMode())
                            {{-- Fast mode is disabled in tournament mode — show a plain Continue button. --}}
                            <button type="button"
                                    x-data="{ clicked: false }"
                                    @click="if (clicked) return; clicked = true; $dispatch('show-pre-match', '{{ route('game.pre-match-data', $game->id) }}')"
                                    x-bind:disabled="clicked"
                                    class="inline-flex items-center justify-center px-3 py-1.5 min-h-[36px] text-xs rounded-lg bg-accent-blue hover:bg-blue-600 active:bg-blue-700 border border-transparent font-semibold text-white uppercase tracking-wider focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-2 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150">
                                {{ __('app.continue') }}
                            </button>
                        @else
                            {{-- Split button: left half = Continue (pre-match flow), right half = open fast-mode info modal --}}
                            <div class="inline-flex items-stretch">
                                <button type="button"
                                        x-data="{ clicked: false }"
                                        @click="if (clicked) return; clicked = true; $dispatch('show-pre-match', '{{ route('game.pre-match-data', $game->id) }}')"
                                        x-bind:disabled="clicked"
                                        class="inline-flex items-center justify-center px-3 py-1.5 min-h-[36px] text-xs rounded-l-lg bg-accent-blue hover:bg-blue-600 active:bg-blue-700 border border-transparent font-semibold text-white uppercase tracking-wider focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-2 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150">
                                    {{ __('app.continue') }}
                                </button>
                                <button type="button"
                                        @click="$dispatch('open-modal', 'fast-mode-info')"
                                        aria-label="{{ __('game.fast_mode_enter') }}"
                                        class="inline-flex items-center justify-center px-2 min-h-[36px] rounded-r-lg bg-accent-blue hover:bg-blue-600 active:bg-blue-700 border border-transparent border-l border-l-blue-700/60 text-white focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-2 focus:ring-offset-surface-900 transition ease-in-out duration-150">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                        <path fill-rule="evenodd" d="M9.58 1.077a.75.75 0 0 1 .405.82L9.165 6h4.085a.75.75 0 0 1 .567 1.241l-6.5 7.5a.75.75 0 0 1-1.302-.638L6.835 10H2.75a.75.75 0 0 1-.567-1.241l6.5-7.5a.75.75 0 0 1 .897-.182Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        @endif
                    @else
                        <div class="flex items-center gap-3">
                            <x-primary-button-link size="sm" color="amber" :href="route($game->isTournamentMode() ? 'game.tournament-end' : 'game.season-end', $game->id)">
                                {{ __('game.view_season_summary') }}
                            </x-primary-button-link>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    {{-- Fast Mode Info Modal (opened by split-button chevron) --}}
    @if($nextMatch && !$game->hasPendingActions() && !$continueToHome && !$game->isFastMode() && !$game->isTournamentMode())
        @include('partials.fast-mode-info-modal')
    @endif

    {{-- Pre-Match Confirmation Modal --}}
    @if($nextMatch && !$game->hasPendingActions() && !$continueToHome)
    <div x-data="preMatchLoader()" x-on:show-pre-match.window="loadPreMatch($event.detail)">
        <form x-ref="autoAdvanceForm" method="POST" action="{{ route('game.advance', $game->id) }}" class="hidden">
            @csrf
        </form>
        <x-modal name="pre-match" maxWidth="lg">
            <x-modal-header modalName="pre-match">{{ __('messages.pre_match_title') }}</x-modal-header>
            <div class="p-4 md:p-6">
                {{-- Loading spinner --}}
                <div x-show="loading" class="flex items-center justify-center py-12">
                    <svg class="animate-spin h-8 w-8 text-text-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                {{-- Server-rendered content --}}
                <div x-show="!loading" x-html="content"></div>
            </div>
        </x-modal>
    </div>
    @endif

    {{-- Mobile Notifications Modal (triggered by header bell icon) --}}
    <div class="lg:hidden" x-data>
        <x-modal name="notifications-mobile" maxWidth="lg">
            <x-modal-header modalName="notifications-mobile">{{ __('notifications.inbox') }}</x-modal-header>

            @if($unreadCount > 0)
            <div class="px-4 py-2.5 border-b border-border-default">
                <span class="px-1.5 py-0.5 rounded-full bg-accent-blue/10 text-[10px] font-semibold text-accent-blue">
                    {{ $unreadCount }} {{ __('notifications.new') }}
                </span>
            </div>
            @endif

            <div class="max-h-[70vh] overflow-y-auto">
                @if($recentNotifications->isEmpty())
                <div class="text-center py-8 px-4">
                    <div class="text-text-faint mb-2">
                        <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-xs text-text-muted">{{ __('notifications.all_caught_up') }}</p>
                </div>
                @else
                <x-notification-inbox-list :notifications="$recentNotifications" :game="$game" />
                @endif
            </div>
        </x-modal>
    </div>

    {{-- Critical-alert popup: blocking, must-dismiss alert for the highest-stakes
         (PRIORITY_CRITICAL) notifications. Renders nothing when none are pending. --}}
    <x-critical-alert-modal :alerts="$criticalAlerts" :game="$game" />

    {{-- Mobile Bottom Tab Bar --}}
    <x-bottom-tab-bar :game="$game" :next-match="$nextMatch" :team-competitions="$teamCompetitions" />

    {{-- Matchday-advance overlay. Shown instantly (client-side) on submit of any
         form that POSTs to game.advance so the user sees the branded loading
         screen while the HTTP request runs the advance inline. Listens for a
         window-level "matchday-advance-starting" event dispatched by those
         forms. If the request fails or the browser is refreshed mid-flight,
         ShowGame still renders game-loading-matchday as the server-side
         fallback. --}}
    <div x-data="{ visible: false }"
         x-show="visible"
         x-on:matchday-advance-starting.window="visible = true"
         x-cloak
         style="display: none"
         class="fixed inset-0 z-[100] bg-surface-900 flex items-start md:items-center justify-center pt-24 md:pt-0 pb-8">
        <div class="w-full max-w-md px-4">
            @if($nextMatch)
                @php($comp = $nextMatch->competition)
                <div class="text-center mb-8">
                    <x-competition-pill :competition="$comp" class="justify-center mb-2" />
                    <h1 class="text-lg md:text-2xl font-bold text-text-primary">
                        @if($nextMatch->round_name)
                            {{ __($nextMatch->round_name) }}
                        @elseif($nextMatch->round_number)
                            {{ __('game.matchday_n', ['number' => $nextMatch->round_number]) }}
                        @endif
                    </h1>
                    <p class="text-sm text-text-muted mt-1">
                        {{ $nextMatch->venueName() ?? '' }} &middot; {{ $nextMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                    </p>
                </div>

                <div class="flex items-center justify-center gap-4 md:gap-8 mb-8">
                    <div class="flex-1 flex flex-col items-center text-center min-w-0">
                        <x-team-crest :team="$nextMatch->homeTeam" class="w-16 h-16 md:w-24 md:h-24 mb-2" />
                        <h4 class="text-sm md:text-base font-bold text-text-primary truncate max-w-full">{{ $nextMatch->homeTeam->short_name ?? $nextMatch->homeTeam->name }}</h4>
                    </div>
                    <div class="shrink-0">
                        <span class="text-lg md:text-2xl font-black text-text-body tracking-tight">{{ __('game.vs') }}</span>
                    </div>
                    <div class="flex-1 flex flex-col items-center text-center min-w-0">
                        <x-team-crest :team="$nextMatch->awayTeam" class="w-16 h-16 md:w-24 md:h-24 mb-2" />
                        <h4 class="text-sm md:text-base font-bold text-text-primary truncate max-w-full">{{ $nextMatch->awayTeam->short_name ?? $nextMatch->awayTeam->name }}</h4>
                    </div>
                </div>
            @endif

            <div class="text-center">
                <div class="flex justify-center mb-3">
                    <svg class="animate-spin h-6 w-6 text-accent-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <p class="text-sm text-text-secondary">{{ __('game.simulating_matches_message') }}</p>
            </div>
        </div>
    </div>
</div>
