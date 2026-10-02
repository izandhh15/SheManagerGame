@php
/** @var App\Models\Game $game */
/** @var \Illuminate\Support\Collection $posts */
/** @var string $nationalHandle */
/** @var string|null $instagramHandle */
/** @var bool $isFederationAccount */
/** @var string $followers */
/** @var App\Models\GameMatch|null $nextHome */
/** @var bool $hasSquad */
/** @var int $squadCount */
/** @var array $ticketTiers */
$assetUrl = rtrim(Storage::disk('assets')->url(''), '/');
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 pb-8">
        {{-- National team profile header --}}
        <div class="mt-6 mb-4 p-4 rounded-xl bg-surface-800 border border-border-default">
            <div class="flex items-center gap-3">
                <x-team-crest :team="$game->team" class="w-14 h-14 shrink-0" />
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-text-primary flex items-center gap-1.5">
                        {{ $game->team?->name }}
                        <span class="text-sky-400" title="{{ __('game.club_social_official_badge') }}">✓</span>
                    </div>
                    <div class="text-text-faint text-xs">{{ $nationalHandle }}</div>
                    @if($instagramHandle)
                        <div class="text-text-faint text-xs">{{ $instagramHandle }} · Instagram</div>
                    @endif
                    <div class="text-text-secondary text-xs mt-0.5">{{ $followers }} {{ __('game.club_social_followers') }}</div>
                    @if($isFederationAccount)
                        <div class="text-[11px] text-amber-400 mt-0.5">{{ __('game.national_social_federation_note') }}</div>
                    @endif
                </div>
            </div>
            <p class="text-xs text-text-secondary mt-3">{{ __('game.national_social_subtitle') }}</p>
        </div>

        <x-flash-message type="error" :message="session('error')" class="mb-4" />
        <x-flash-message type="success" :message="session('success')" class="mb-4" />

        {{-- Composer --}}
        <div class="mb-6 p-4 rounded-xl bg-surface-800 border border-border-default">
            <h3 class="font-bold text-text-primary mb-3">📢 {{ __('game.national_social_compose') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {{-- Convocatoria --}}
                <form method="POST" action="{{ route('game.national-social.announce', $game->id) }}" class="p-3 rounded-lg border border-border-default bg-surface-700 flex flex-col">
                    @csrf
                    <input type="hidden" name="type" value="convocatoria">
                    <p class="text-sm font-semibold text-text-primary mb-2">📋 {{ __('game.national_social_type_convocatoria') }}</p>
                    @if($hasSquad)
                        <p class="text-xs text-text-secondary mb-2 flex-1">{{ __('game.national_social_convocatoria_ready', ['count' => $squadCount]) }}</p>
                        <x-primary-button type="submit" class="w-full text-xs mt-auto">{{ __('game.club_social_publish') }}</x-primary-button>
                    @else
                        <p class="text-xs text-text-faint mb-2 flex-1">{{ __('game.national_social_no_squad_hint') }}</p>
                        <x-primary-button type="submit" disabled class="w-full text-xs mt-auto">{{ __('game.club_social_publish') }}</x-primary-button>
                    @endif
                </form>
                {{-- Sede --}}
                <form method="POST" action="{{ route('game.national-social.announce', $game->id) }}" class="p-3 rounded-lg border border-border-default bg-surface-700 flex flex-col">
                    @csrf
                    <input type="hidden" name="type" value="sede">
                    <p class="text-sm font-semibold text-text-primary mb-2">🏟️ {{ __('game.national_social_type_sede') }}</p>
                    @if($nextHome && ($nextHome->stadium_name || $nextHome->neutral_venue_name))
                        <p class="text-xs text-text-secondary mb-2 flex-1">{{ $nextHome->stadium_name ?? $nextHome->neutral_venue_name }}</p>
                        <x-primary-button type="submit" class="w-full text-xs mt-auto">{{ __('game.club_social_publish') }}</x-primary-button>
                    @else
                        <p class="text-xs text-text-faint mb-2 flex-1">{{ __('game.national_social_no_match_hint') }}</p>
                        <x-primary-button type="submit" disabled class="w-full text-xs mt-auto">{{ __('game.club_social_publish') }}</x-primary-button>
                    @endif
                </form>
                {{-- Entradas --}}
                <form method="POST" action="{{ route('game.national-social.announce', $game->id) }}" class="p-3 rounded-lg border border-border-default bg-surface-700 flex flex-col">
                    @csrf
                    <input type="hidden" name="type" value="entradas">
                    <p class="text-sm font-semibold text-text-primary mb-2">🎟️ {{ __('game.national_social_type_entradas') }}</p>
                    <select name="tier" class="w-full mb-2 text-sm rounded-lg bg-surface-800 border-border-default text-text-primary flex-1">
                        @foreach($ticketTiers as $tier => $price)
                            <option value="{{ $tier }}">{{ __('game.national_social_tier_' . $tier) }} — {{ $price }}€</option>
                        @endforeach
                    </select>
                    <x-primary-button type="submit" class="w-full text-xs mt-auto">{{ __('game.club_social_publish') }}</x-primary-button>
                </form>
            </div>
        </div>

        {{-- Official feed --}}
        <div class="space-y-3">
            @forelse($posts as $post)
                <div class="p-4 rounded-xl bg-surface-800 border border-sky-500/30">
                    <div class="flex items-center gap-2 mb-2">
                        <x-team-crest :team="$game->team" class="w-10 h-10 shrink-0" />
                        <div class="flex-1">
                            <div class="font-semibold text-text-primary text-sm">
                                {{ $post->author_name }} <span class="text-sky-400">✓</span>
                            </div>
                            <div class="text-text-faint text-xs">{{ $post->author_handle }}</div>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wide text-sky-400 bg-sky-500/10 border border-sky-500/30 rounded-full px-2 py-0.5">📢 {{ __('game.club_social_official_badge') }}</span>
                    </div>
                    <p class="text-text-primary text-sm leading-relaxed font-medium">{{ $post->text }}</p>
                    <div class="flex items-center gap-4 mt-2 text-xs text-text-faint">
                        <span>❤️ {{ $post->likes }}</span>
                        <span>💬 {{ $post->replies->count() }}</span>
                        <span>{{ $post->created_at->diffForHumans() }}</span>
                    </div>

                    {{-- Fan replies --}}
                    @foreach($post->replies as $reply)
                        <div class="mt-3 ml-4 p-3 rounded-lg bg-surface-700/60 border {{ $reply->sentiment < 0 ? 'border-red-500/20' : 'border-green-500/20' }}">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white text-xs font-bold">
                                    {{ substr($reply->author_name, 0, 1) }}
                                </div>
                                <div>
                                    <span class="text-xs font-semibold text-text-primary">{{ $reply->author_name }}</span>
                                    <span class="text-text-faint text-[11px] ml-1">{{ $reply->author_handle }}</span>
                                </div>
                                <span class="ml-auto text-sm">{{ $reply->sentiment < 0 ? '👎' : '👍' }}</span>
                            </div>
                            <p class="text-text-primary text-[13px] leading-relaxed">{{ $reply->text }}</p>
                            <div class="text-[11px] text-text-faint mt-1">🤍 {{ $reply->likes }} · {{ $reply->created_at->diffForHumans() }}</div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="p-6 text-center text-text-secondary text-sm rounded-xl bg-surface-800 border border-border-default">
                    {{ __('game.national_social_no_posts') }}
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
