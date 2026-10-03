<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 pb-32">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">🏆 {{ __('friends.careers_title', ['name' => '@' . $friend->username]) }}</h2>
        </div>

        {{-- Active saves --}}
        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-secondary mb-3">🟢 {{ __('friends.active_title') }} ({{ $games->count() }})</h3>
        @if($games->isEmpty())
            <p class="text-xs text-text-muted mb-6">{{ __('friends.no_active', ['name' => '@' . $friend->username]) }}</p>
        @else
            <ul role="list" class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 mb-8">
                @foreach($games as $game)
                    <li class="col-span-1 flex flex-col rounded-lg bg-surface-800 text-center shadow-sm border border-border-default">
                        <div class="flex flex-1 flex-col p-8 space-y-3">
                            <x-team-crest :team="$game->team" class="object-cover mx-auto h-20 w-20 shrink-0" />
                            <h3 class="text-xl font-semibold leading-tight text-text-primary">{{ $game->team->name }}</h3>
                            <dl class="flex flex-col justify-between">
                                <dd class="mb-1">
                                    <x-game-mode-badge :game="$game" />
                                </dd>
                                @if($game->competition)
                                    <dd class="flex items-center justify-center gap-2 mt-1">
                                        <x-competition-logo :competition="$game->competition" class="w-7 h-7 shrink-0" />
                                        <span class="text-xs text-text-secondary">{{ $game->competition->name ? __($game->competition->name) : $game->competition->id }}</span>
                                    </dd>
                                @endif
                                <hr class="pt-4 mt-4 border-t border-border-default">
                                @if($game->season)
                                    <dd class="mt-2 text-xs text-text-muted">{{ __('friends.season_label', ['season' => $game->season]) }}</dd>
                                @endif
                                @if($game->current_date)
                                    <dd class="mt-1 text-xs text-text-muted">{{ \Carbon\Carbon::parse($game->current_date)->format('d/m/Y') }}</dd>
                                @endif
                            </dl>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Deleted history --}}
        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-secondary mb-3">📜 {{ __('friends.history_title') }} ({{ $history->count() }})</h3>
        @if($history->isEmpty())
            <p class="text-xs text-text-muted mb-6">{{ __('friends.no_history') }}</p>
        @else
            <div class="space-y-2 mb-8">
                @foreach($history as $row)
                    @php $s = $row->stats ?? []; @endphp
                    <div class="rounded-lg border border-border-default bg-surface-800 px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-text-primary truncate">{{ $row->team_name }}</p>
                                <p class="text-xs text-text-muted">
                                    {{ $row->team_type === 'national' ? __('friends.type_national') : __('friends.type_club') }}
                                    @if($row->season) · {{ __('friends.season_label', ['season' => $row->season]) }}@endif
                                </p>
                            </div>
                            <span class="text-[11px] text-text-faint">{{ __('friends.deleted_on', ['date' => $row->deleted_at?->format('d/m/Y') ?? '—']) }}</span>
                        </div>
                        @if(!empty($s))
                            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-text-secondary">
                                <span>⚽ {{ __('friends.stats_matches', ['count' => $s['matches'] ?? 0]) }}</span>
                                <span>{{ __('friends.stats_record', ['wins' => $s['wins'] ?? 0, 'draws' => $s['draws'] ?? 0, 'losses' => $s['losses'] ?? 0]) }}</span>
                                <span>🏆 {{ __('friends.stats_trophies', ['count' => $s['trophies'] ?? 0]) }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            <a href="{{ route('friends.index') }}" class="text-sm text-accent-blue underline">← {{ __('friends.back') }}</a>
        </div>
    </div>
</x-app-layout>
