{{--
    Game breadcrumb: Inicio / Sección / Página.
    Rendered under the game header. Renders nothing on the dashboard or unknown routes.
--}}
@props(['game' => null, 'teamCompetitions' => null])

@php
    $crumbRoute = Route::currentRouteName();
    $crumbSection = null;   // ['label' => ..., 'url' => ...] — section link goes to its landing page
    $crumbPage = null;      // ['label' => ..., 'url' => ...]

    $sectionUrl = fn($name) => $game ? route($name, $game->id) : null;

    switch ($crumbRoute) {
        // 📋 MI EQUIPO
        case 'game.squad':
            $crumbSection = ['label' => __('app.my_team'), 'url' => $sectionUrl('game.squad')];
            $crumbPage = ['label' => __('squad.first_team'), 'url' => null];
            break;
        case 'game.lineup':
            $crumbSection = ['label' => __('app.my_team'), 'url' => $sectionUrl('game.squad')];
            $crumbPage = ['label' => __('app.starting_xi'), 'url' => null];
            break;
        case 'game.squad.planner':
            $crumbSection = ['label' => __('app.my_team'), 'url' => $sectionUrl('game.squad')];
            $crumbPage = ['label' => __('app.tactics'), 'url' => null];
            break;
        case 'game.squad.registration':
            $crumbSection = ['label' => __('app.my_team'), 'url' => $sectionUrl('game.squad')];
            $crumbPage = ['label' => __('squad.registration'), 'url' => null];
            break;
        case 'game.squad.reserve':
            $crumbSection = ['label' => __('app.my_team'), 'url' => $sectionUrl('game.squad')];
            $crumbPage = ['label' => __('squad.reserve_team'), 'url' => null];
            break;
        case 'game.opponent-analysis':
            $crumbSection = ['label' => __('app.my_team'), 'url' => $sectionUrl('game.squad')];
            $crumbPage = ['label' => __('app.scout_opponent'), 'url' => null];
            break;
        case 'game.player.detail':
            // {playerId} is a GamePlayer UUID (ShowPlayerDetail::findOrFail),
            // never a row in `players` — look it up in the game's own roster.
            $crumbPlayerName = null;
            if ($game && request()->route('playerId')) {
                $crumbPlayer = \App\Models\GamePlayer::where('game_id', $game->id)
                    ->find(request()->route('playerId'));
                $crumbPlayerName = $crumbPlayer?->name;
            }
            $crumbSection = ['label' => __('app.my_team'), 'url' => $sectionUrl('game.squad')];
            $crumbPage = ['label' => $crumbPlayerName ?? __('squad.first_team'), 'url' => null];
            break;

        // 🔄 FICHAJES
        case 'game.transfers':
            $crumbSection = ['label' => __('app.transfers'), 'url' => $sectionUrl('game.transfers')];
            $crumbPage = ['label' => __('transfers.incoming'), 'url' => null];
            break;
        case 'game.transfers.outgoing':
            $crumbSection = ['label' => __('app.transfers'), 'url' => $sectionUrl('game.transfers')];
            $crumbPage = ['label' => __('transfers.outgoing'), 'url' => null];
            break;
        case 'game.transfers.market':
            $crumbSection = ['label' => __('app.transfers'), 'url' => $sectionUrl('game.transfers')];
            $crumbPage = ['label' => __('transfers.market_tab'), 'url' => null];
            break;
        case 'game.scouting':
        case 'game.scouting.results':
            $crumbSection = ['label' => __('app.transfers'), 'url' => $sectionUrl('game.transfers')];
            $crumbPage = ['label' => __('transfers.scouting_tab'), 'url' => null];
            break;
        case 'game.explore':
        case 'game.explore.teams':
        case 'game.explore.squad':
        case 'game.explore.pool-teams':
        case 'game.explore.team':
            $crumbSection = ['label' => __('app.transfers'), 'url' => $sectionUrl('game.transfers')];
            $crumbPage = ['label' => __('transfers.explore_tab'), 'url' => null];
            break;
        case 'game.transfer-activity':
            $crumbSection = ['label' => __('app.transfers'), 'url' => $sectionUrl('game.transfers')];
            $crumbPage = ['label' => __('transfers.incoming'), 'url' => null];
            break;
        case 'game.squad.academy':
        case 'game.academy.detail':
            $crumbSection = ['label' => __('app.transfers'), 'url' => $sectionUrl('game.transfers')];
            $crumbPage = ['label' => __('squad.academy'), 'url' => null];
            break;

        // 🏟️ CLUB
        case 'game.club.finances':
        case 'game.club':
            $crumbSection = ['label' => __('app.club'), 'url' => $sectionUrl('game.club.finances')];
            $crumbPage = ['label' => __('club.nav.finances'), 'url' => null];
            break;
        case 'game.club.investment':
            $crumbSection = ['label' => __('app.club'), 'url' => $sectionUrl('game.club.finances')];
            $crumbPage = ['label' => __('club.nav.investment'), 'url' => null];
            break;
        case 'game.club.stadium':
            $crumbSection = ['label' => __('app.club'), 'url' => $sectionUrl('game.club.finances')];
            $crumbPage = ['label' => __('club.nav.stadium'), 'url' => null];
            break;
        case 'game.club.commercial':
            $crumbSection = ['label' => __('app.club'), 'url' => $sectionUrl('game.club.finances')];
            $crumbPage = ['label' => __('club.nav.commercial'), 'url' => null];
            break;
        case 'game.club.reputation':
            $crumbSection = ['label' => __('app.club'), 'url' => $sectionUrl('game.club.finances')];
            $crumbPage = ['label' => __('club.nav.reputation'), 'url' => null];
            break;

        // 📅 COMPETICIÓN
        case 'game.calendar':
            $crumbSection = ['label' => __('app.competitions'), 'url' => $sectionUrl('game.calendar')];
            $crumbPage = ['label' => __('app.calendar'), 'url' => null];
            break;
        case 'game.results':
            $crumbSection = ['label' => __('app.competitions'), 'url' => $sectionUrl('game.calendar')];
            $crumbPage = ['label' => __('app.results'), 'url' => null];
            break;
        case 'game.competition':
            $crumbCompName = null;
            if ($teamCompetitions && request()->route('competitionId')) {
                // Competition uses a string key ('ESP1'…): no (int) cast —
                // (int)'ESP1' === 0 would never match.
                $crumbComp = $teamCompetitions->firstWhere('id', request()->route('competitionId'));
                $crumbCompName = $crumbComp ? __($crumbComp->name) : null;
            }
            $crumbSection = ['label' => __('app.competitions'), 'url' => $sectionUrl('game.calendar')];
            $crumbPage = ['label' => $crumbCompName ?? __('game.standings'), 'url' => null];
            break;
        case 'game.match.summary':
        case 'game.live-match':
            $crumbSection = ['label' => __('app.competitions'), 'url' => $sectionUrl('game.calendar')];
            $crumbPage = ['label' => __('app.matches'), 'url' => null];
            break;

        // 🇪🇸 SELECCIÓN
        case 'game.national-squad-picker':
            $crumbSection = ['label' => __('app.selection'), 'url' => $sectionUrl('game.national-squad-picker')];
            $crumbPage = ['label' => __('game.convocatoria'), 'url' => null];
            break;

        // 💼 CARRERA
        case 'game.manager.career':
            $crumbSection = ['label' => __('manager.career_title'), 'url' => $sectionUrl('game.manager.career')];
            $crumbPage = null;
            break;
    }
@endphp

@if($game && ($crumbSection || $crumbPage))
<nav aria-label="breadcrumb" class="hidden md:block border-b border-border-default bg-surface-800/60">
    <ol class="max-w-[1600px] mx-auto px-4 py-1.5 flex items-center gap-1.5 text-xs text-text-muted overflow-x-auto whitespace-nowrap">
        <li>
            <a href="{{ route('show-game', $game->id) }}" class="hover:text-text-primary transition-colors">{{ __('app.dashboard') }}</a>
        </li>
        @if($crumbSection)
        <li aria-hidden="true" class="text-text-faint">/</li>
        <li>
            @if($crumbSection['url'])
            <a href="{{ $crumbSection['url'] }}" class="hover:text-text-primary transition-colors">{{ $crumbSection['label'] }}</a>
            @else
            <span>{{ $crumbSection['label'] }}</span>
            @endif
        </li>
        @endif
        @if($crumbPage)
        <li aria-hidden="true" class="text-text-faint">/</li>
        <li aria-current="page" class="text-text-primary font-medium truncate max-w-[320px]">{{ $crumbPage['label'] }}</li>
        @endif
    </ol>
</nav>
@endif
