@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$nextMatch"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        {{-- Pending action alert --}}
        @if($game->hasPendingActions())
            @php $pendingAction = $game->getFirstPendingAction(); @endphp
            <x-status-banner color="gold" :title="__('messages.action_required')" class="mt-6">
                <x-slot name="icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </x-slot>
                @if($pendingAction && $pendingAction['route'])
                <x-primary-button-link color="amber" :href="route($pendingAction['route'], $game->id)" class="shrink-0">
                    {{ __('messages.action_required_short') }}
                </x-primary-button-link>
                @endif
            </x-status-banner>
        @endif

        {{-- Dual-mode forced switch: this half is frozen until the partner's
             earlier match is played (strict club ⇄ nation alternation). --}}
        @if(!empty($dualForcedPartner))
            <x-status-banner color="red" :title="__('game.dual_forced_title')" :description="__('game.dual_forced_blocked', ['mine' => $game->team->name, 'partner' => $dualForcedPartner->team->name])" class="mt-6">
                <x-slot name="icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot>
                <x-primary-button-link color="red" :href="route('show-game', $dualForcedPartner->id)" class="shrink-0">
                    {{ __('game.dual_forced_cta', ['team' => $dualForcedPartner->team->name]) }}
                </x-primary-button-link>
            </x-status-banner>
        @endif

        {{-- Just bounced here by the forced switch: play this half first. --}}
        @if(session('dual_forced'))
            @php $forcedInfo = session('dual_forced'); @endphp
            <x-status-banner color="gold" :title="$forcedInfo['to_nation'] ?? true ? __('game.dual_forced_bounced_title') : __('game.dual_forced_back_title')" :description="__('game.dual_forced_bounced', ['team' => $forcedInfo['team'], 'from' => $forcedInfo['from']])" class="mt-6">
                <x-slot name="icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </x-slot>
            </x-status-banner>
        @endif

        {{-- Dual-mode switcher: this save is linked to another save (club ⇄
             nation). They are SEPARATE simulations — injuries, form and
             calendars never cross over — but the turn order is strict: the
             half with the earlier match must be played first. --}}
        @php $dualPartner = $game->dualPartner(); @endphp
        @if($dualPartner)
        <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-accent-green/30 bg-accent-green/5 px-4 py-3">
            <span class="text-[10px] font-semibold uppercase tracking-widest text-accent-green">{{ $game->isAffiliatePair() ? __('game.affiliate_mode') : __('game.dual_mode') }}</span>
            <a href="{{ route('show-game', $dualPartner->id) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-text-body hover:text-accent-green transition-colors">
                <x-team-crest :team="$dualPartner->team" class="w-6 h-6" />
                @if($game->isAffiliatePair())
                    {{ $game->isDualSecondary() ? __('game.affiliate_go_first') : __('game.affiliate_go_reserve') }}: {{ $dualPartner->team->name }}
                @else
                    {{ $game->isDualSecondary() ? __('game.dual_go_club') : __('game.dual_go_nation') }}: {{ $dualPartner->team->name }}
                @endif
            </a>
            <form action="{{ route('game.advance-both', $game->id) }}" method="POST" class="ml-auto" x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault(); return; } submitting = true; $dispatch('matchday-advance-starting')">
                @csrf
                <x-primary-button color="green" size="sm" x-bind:disabled="submitting">
                    {{ __('game.advance_both') }}
                </x-primary-button>
            </form>
        </div>
        @endif

        {{-- ⚡ Acciones rápidas: atajos a lo que más se usa, sin pasar por los menús --}}
        @php
            $quickActions = [];
            if ($nextMatch) {
                $quickActions[] = ['route' => 'game.lineup', 'label' => __('app.play_match'), 'icon' => 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 0 1 0 1.971l-11.54 6.347a1.125 1.125 0 0 1-1.667-.985V5.653Z', 'highlight' => true];
            }
            if ($game->isCareerMode()) {
                $quickActions[] = ['route' => 'game.transfers.market', 'label' => __('transfers.market_tab'), 'icon' => 'M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72L4.318 3.44A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72m-13.5 8.615c0 .331.27.6.6.6h3.6a.6.6 0 0 0 .6-.6v-1.2a.6.6 0 0 0-.6-.6h-3.6a.6.6 0 0 0-.6.6v1.2Z', 'highlight' => false];
                $quickActions[] = ['route' => 'game.transfers', 'label' => __('app.renewals'), 'icon' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99', 'highlight' => false];
                $quickActions[] = ['route' => 'game.scouting', 'label' => __('transfers.scouting_tab'), 'icon' => 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z', 'highlight' => false];
                if (!$game->isFilial()) {
                    $quickActions[] = ['route' => 'game.squad.academy', 'label' => __('squad.academy'), 'icon' => 'M4.26 10.147a60.436 60.436 0 0 0-.491 6.347A48.627 48.627 0 0 1 12 20.904a48.627 48.627 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.57 50.57 0 0 0-2.658-.813A59.905 59.905 0 0 1 12 3.493a59.902 59.902 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5', 'highlight' => false];
                }
                $quickActions[] = ['route' => 'game.club.stadium', 'label' => __('club.nav.stadium'), 'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21', 'highlight' => false];
            }
            $quickActions[] = ['route' => 'game.calendar', 'label' => __('app.calendar'), 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5', 'highlight' => false];
            if ($game->team->type === 'national') {
                $quickActions[] = ['route' => 'game.national-squad-picker', 'label' => __('game.convocatoria'), 'icon' => 'M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25', 'highlight' => false];
                // Venue organization for Nations League / qualifiers home matches.
                $pendingVenues = $pendingVenues ?? 0;
                $quickActions[] = [
                    'route' => 'game.national-venues',
                    'label' => __('game.venue_org_quick') . ($pendingVenues > 0 ? " ({$pendingVenues})" : ''),
                    'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
                    'highlight' => $pendingVenues > 0,
                ];
            }
        @endphp
        @if(!empty($quickActions))
        <x-section-card :title="__('app.quick_actions')" class="mt-6">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 px-4 py-4">
                @foreach($quickActions as $action)
                <a href="{{ route($action['route'], $game->id) }}"
                   class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg border transition-colors text-sm font-medium
                          {{ $action['highlight'] ? 'bg-accent-red/10 border-accent-red/40 text-text-primary hover:bg-accent-red/20' : 'bg-surface-700/40 border-border-default text-text-body hover:bg-surface-700 hover:text-text-primary' }}">
                    <svg class="w-5 h-5 shrink-0 {{ $action['highlight'] ? 'text-accent-red' : 'text-text-muted' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $action['icon'] }}"/>
                    </svg>
                    {{ $action['label'] }}
                </a>
                @endforeach
            </div>
        </x-section-card>
        @endif

        @if($nextMatch)
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
            {{-- Context rail: next match + fixtures + standings. The narrow 1/3
                 column on desktop (md:order-2); on mobile it stacks first (DOM
                 order) so the "play match" flow stays at the top. --}}
            <div class="space-y-8 md:order-2 md:col-span-1">
                {{-- Highlighted Next Match Card --}}
                @include('partials.next-match-card')

                {{-- Remaining Upcoming Fixtures / Next Round Preview --}}
                @if($upcomingFixtures->skip(1)->isNotEmpty())
                <x-section-card :title="__('game.upcoming_fixtures')">
                    <x-slot name="badge">
                        <a href="{{ route('game.calendar', $game->id) }}" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
                            {{ __('game.full_calendar') }} &rarr;
                        </a>
                    </x-slot>
                    <div class="divide-y divide-border-default">
                        @foreach($upcomingFixtures->skip(1)->take(4) as $fixture)
                            <x-fixture-row :match="$fixture" :game="$game" :show-score="false" :highlight-next="false" :short-competition="true" />
                        @endforeach
                    </div>
                </x-section-card>
                @elseif(isset($nextRoundPreview))
                <x-section-card :title="__('game.upcoming_fixtures')">
                    <div class="flex items-center gap-3 px-4 py-2.5">
                        <div class="w-10 shrink-0 text-center">
                            <span class="text-[9px] text-text-faint uppercase">TBD</span>
                        </div>
                        <div class="flex-1 flex items-center gap-2 min-w-0">
                            @if($nextRoundPreview['opponent'])
                                <x-team-crest :team="$nextRoundPreview['opponent']" class="w-5 h-5 shrink-0" />
                                <span class="text-xs text-text-body">{{ $nextRoundPreview['opponent']->name }}</span>
                            @else
                                <div class="flex items-center gap-1.5">
                                    <x-team-crest :team="$nextRoundPreview['tie']->homeTeam" class="w-5 h-5 shrink-0" />
                                    <span class="text-xs text-text-secondary truncate">{{ $nextRoundPreview['tie']->homeTeam->name }}</span>
                                    <span class="text-xs text-text-muted">/</span>
                                    <x-team-crest :team="$nextRoundPreview['tie']->awayTeam" class="w-5 h-5 shrink-0" />
                                    <span class="text-xs text-text-secondary truncate">{{ $nextRoundPreview['tie']->awayTeam->name }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </x-section-card>
                @endif

                {{-- Contextual standings or cup-path card (hidden during pre-season).
                     The card follows the competition of the *next* match, not the
                     primary league, so European/cup matchdays surface the right table. --}}
                @if(empty($isPreSeason))
                    @if($dashboardContext['mode'] === 'league' && $dashboardContext['standings']->isNotEmpty())
                    <x-section-card :title="$dashboardContext['title']">
                        <x-slot name="badge">
                            <a href="{{ route('game.competition', [$game->id, $dashboardContext['competition']->id]) }}" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
                                {{ __('game.full_table') }} &rarr;
                            </a>
                        </x-slot>

                        {{-- Column headers --}}
                        <div class="grid grid-cols-[24px_1fr_28px_28px_28px_32px_36px] gap-1 px-4 py-2 text-[9px] text-text-faint uppercase tracking-wider border-b border-border-default">
                            <span>#</span>
                            <span>{{ __('game.team') }}</span>
                            <span class="text-center">{{ __('game.won_abbr') }}</span>
                            <span class="text-center">{{ __('game.drawn_abbr') }}</span>
                            <span class="text-center">{{ __('game.lost_abbr') }}</span>
                            <span class="text-center">{{ __('game.goal_diff_abbr') }}</span>
                            <span class="text-right">{{ __('game.pts_abbr') }}</span>
                        </div>

                        {{-- Rows --}}
                        <div class="divide-y divide-border-default">
                            @php $prevPosition = 0; @endphp
                            @foreach($dashboardContext['standings'] as $standing)
                                <x-standing-row
                                    :standing="$standing"
                                    :is-player="$standing->team_id === $game->team_id"
                                    :show-gap="$standing->position > $prevPosition + 1"
                                />
                                @php $prevPosition = $standing->position; @endphp
                            @endforeach
                        </div>
                    </x-section-card>
                    @elseif($dashboardContext['mode'] === 'knockout' && $dashboardContext['playerTie'])
                    <x-cup-path-card
                        :game="$game"
                        :competition="$dashboardContext['competition']"
                        :player-tie="$dashboardContext['playerTie']"
                        :rounds-remaining="$dashboardContext['roundsRemaining']"
                        :final-venue="$dashboardContext['finalVenue']"
                    />
                    @endif
                @endif
            </div>

            <hr class="border-border-strong md:hidden" />

            {{-- Wide 2/3 column on desktop (md:order-1): the notifications inbox
                 leads with actionable per-matchday events, then News sets the scene. --}}
            <div class="space-y-4 md:space-y-6 md:order-1 md:col-span-2">
                @if($showInbox)
                <x-section-card :title="__('notifications.inbox')">
                    <x-slot name="badge">
                        @if($unreadNotificationCount > 0)
                        <span class="px-1.5 py-0.5 rounded-full bg-accent-blue/10 text-[9px] font-semibold text-accent-blue">
                            {{ $unreadNotificationCount }} {{ __('notifications.new') }}
                        </span>
                        @endif
                    </x-slot>

                    @if($groupedNotifications->isEmpty())
                    <div class="flex items-center gap-2 px-5 py-2.5 text-xs text-text-muted">
                        <svg class="w-4 h-4 text-text-faint shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ __('notifications.all_caught_up') }}</span>
                    </div>
                    @else
                    <x-notification-inbox-list :notifications="$groupedNotifications->flatten()" :game="$game" />
                    @endif
                </x-section-card>
                @endif

                {{-- News: the league narrative engine surfaced as icon-tagged story
                     items (transfer buzz, rivalry, European nights, form, mood…).
                     Season-based modes surface it here; tournament mode keeps the
                     prose in the next-match card. Leads the column on quiet
                     matchdays when the inbox is empty and hidden. --}}
                @if($showNews)
                    <x-news :narratives="$narratives" :game="$game" />
                @endif
            </div>
        </div>
        @elseif($hasRemainingMatches)
        {{-- AI Matches Remaining State --}}
        <div class="mt-6 bg-surface-800 rounded-xl border border-border-default p-4 md:p-8 text-center">
            <div class="text-text-body mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="text-3xl font-bold text-text-primary mb-2">{{ __('game.other_competitions_in_progress') }}</h2>
            <p class="text-text-muted mb-8">{{ __('game.other_competitions_desc') }}</p>
            <form action="{{ route('game.advance', $game->id) }}" method="POST" x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault(); return; } submitting = true; $dispatch('matchday-advance-starting')">
                @csrf
                <x-primary-button color="red" x-bind:disabled="submitting">
                    {{ __('game.advance_other_matches') }}
                </x-primary-button>
            </form>
        </div>
        @else
        {{-- Season Complete Preview State.
             All matches are played but the user has not yet clicked "Start
             New Season" on the summary page (the irreversible step that
             closes the season). Show a card that points to the summary, but
             leave the rest of the dashboard browsable so the user can take
             one last look at the squad, finances, transfers, and standings. --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
            <div class="md:col-span-2 space-y-8">
                <x-section-card>
                    <div class="p-4 md:p-6 text-center">
                        <div class="text-accent-gold mb-3 inline-flex">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.32.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.32-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                            </svg>
                        </div>
                        <h2 class="text-2xl md:text-3xl font-bold text-text-primary mb-2">{{ __('season.complete_title') }}</h2>
                        <p class="text-text-muted mb-6 max-w-xl mx-auto">{{ __('season.complete_body') }}</p>
                        <x-primary-button-link color="amber" :href="route('game.season-end', $game->id)">
                            {{ __('game.view_season_summary') }}
                        </x-primary-button-link>
                    </div>
                </x-section-card>
            </div>

            <hr class="border-border-strong md:hidden" />

            {{-- Right Column - Notifications (standings panel is hidden when
                 there is no next match; the user can still reach it via the
                 Competitions nav). --}}
            <div class="space-y-8">
                <x-section-card :title="__('notifications.inbox')">
                    <x-slot name="badge">
                        @if($unreadNotificationCount > 0)
                        <span class="px-1.5 py-0.5 rounded-full bg-accent-blue/10 text-[9px] font-semibold text-accent-blue">
                            {{ $unreadNotificationCount }} {{ __('notifications.new') }}
                        </span>
                        @endif
                    </x-slot>

                    @if($groupedNotifications->isEmpty())
                    <div class="flex items-center gap-2 px-5 py-2.5 text-xs text-text-muted">
                        <svg class="w-4 h-4 text-text-faint shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ __('notifications.all_caught_up') }}</span>
                    </div>
                    @else
                    <x-notification-inbox-list :notifications="$groupedNotifications->flatten()" :game="$game" />
                    @endif
                </x-section-card>
            </div>
        </div>
        @endif
    </div>

</x-app-layout>
