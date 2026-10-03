@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <x-match-detail-modal />

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-4 flex items-center justify-between gap-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                {{ __('app.calendar') }} · {{ $monthLabel }}
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('game.calendar', $game->id) }}"
                   class="px-3 py-1.5 rounded-lg bg-surface-600/20 hover:bg-surface-600/40 text-text-secondary text-xs font-semibold uppercase tracking-wider transition-colors">
                    {{ __('game.list_view') }}
                </a>
                <a href="{{ route('game.calendar.month', ['gameId' => $game->id, 'ym' => $prevYm]) }}"
                   class="px-3 py-1.5 rounded-lg bg-accent-blue/10 hover:bg-accent-blue/20 text-accent-blue text-xs font-semibold transition-colors">←</a>
                <a href="{{ route('game.calendar.month', ['gameId' => $game->id, 'ym' => $nextYm]) }}"
                   class="px-3 py-1.5 rounded-lg bg-accent-blue/10 hover:bg-accent-blue/20 text-accent-blue text-xs font-semibold transition-colors">→</a>
            </div>
        </div>

        {{-- Weekday header (Monday first) --}}
        <div class="grid grid-cols-7 gap-1 mb-1">
            @foreach(['mon','tue','wed','thu','fri','sat','sun'] as $d)
                <div class="text-center text-[10px] sm:text-xs font-bold uppercase tracking-widest text-text-muted py-1">
                    {{ __('app.weekday_'.$d) }}
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 gap-1">
            @for($i = 0; $i < $leadBlanks; $i++)
                <div class="min-h-[64px] sm:min-h-[96px] rounded-lg bg-surface-800/30"></div>
            @endfor
            @for($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $dateKey = $ym . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                    $matches = $byDay[$dateKey] ?? [];
                    $isToday = $dateKey === $today;
                @endphp
                <div class="min-h-[64px] sm:min-h-[96px] rounded-lg p-1 sm:p-1.5 {{ $isToday ? 'bg-accent-blue/15 ring-1 ring-accent-blue' : 'bg-surface-800/60' }}">
                    <div class="text-[10px] sm:text-xs font-bold {{ $isToday ? 'text-accent-blue' : 'text-text-muted' }}">{{ $day }}</div>
                    <div class="space-y-1 mt-0.5">
                        @foreach($matches as $match)
                            @php
                                $home = $match->homeTeam?->short_name ?? $match->homeTeam?->name ?? '?';
                                $away = $match->awayTeam?->short_name ?? $match->awayTeam?->name ?? '?';
                                $comp = $match->competition?->shortName() ?? $match->competition_id;
                                $detailUrl = $match->played ? route('game.match.summary', [$game->id, $match->id]) : null;
                            @endphp
                            <div @if($detailUrl) role="button" tabindex="0" @click="$dispatch('show-match-detail', '{{ $detailUrl }}')" class="cursor-pointer" @endif
                                    title="{{ $match->homeTeam?->name }} vs {{ $match->awayTeam?->name }}"
                                    class="w-full text-left rounded px-1 py-0.5 text-[9px] sm:text-[10px] leading-tight {{ $match->played ? 'bg-surface-600/30 text-text-secondary' : 'bg-accent-green/15 text-text-primary' }} transition-colors">
                                <span class="font-semibold">{{ $home }}–{{ $away }}</span>
                                @if($match->played)
                                    <span class="font-bold">{{ $match->home_score }}-{{ $match->away_score }}</span>
                                @endif
                                <span class="block sm:inline text-text-muted truncate">{{ $comp }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endfor
        </div>
    </div>
</x-app-layout>
