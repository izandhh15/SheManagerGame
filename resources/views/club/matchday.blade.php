<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">

        {{-- Club hub title + subnav --}}
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('club.hub_title') }}</h2>
        </div>
        <x-club-section-nav :game="$game" active="matchday" />

        <x-flash-message type="success" :message="session('success')" class="mt-4" />
        <x-flash-message type="error" :message="session('error')" class="mt-4" />

        {{-- Intro --}}
        <div class="mt-6 bg-surface-800 border border-border-default rounded-xl px-5 py-5">
            <h3 class="font-heading text-lg font-bold uppercase tracking-wide text-text-primary">{{ __('club.matchday.title') }}</h3>
            <p class="mt-2 text-sm text-text-secondary leading-relaxed">{{ __('club.matchday.intro') }}</p>
        </div>

        <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">

            {{-- Price controls --}}
            <div>
                <x-section-card :title="__('club.matchday.prices_title')">
                    <form method="POST" action="{{ route('game.club.matchday.save', $game->id) }}" class="px-5 py-5 space-y-4">
                        @csrf
                        @php
                            $fields = [
                                'ticket' => __('club.matchday.ticket_label'),
                                'shirt' => __('club.matchday.shirt_label'),
                                'merch' => __('club.matchday.merch_label'),
                                'bar' => __('club.matchday.bar_label'),
                            ];
                        @endphp
                        @foreach($fields as $key => $label)
                            <div>
                                <label for="{{ $key }}_price" class="block text-sm font-semibold text-text-body mb-1">
                                    {{ $label }}
                                    <span class="font-normal text-text-muted">({{ __('club.matchday.default_is', ['amount' => $defaults[$key]]) }})</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="{{ $key }}_price" id="{{ $key }}_price"
                                           value="{{ $prices[$key] }}"
                                           min="{{ $bands[$key][0] }}" max="{{ $bands[$key][1] }}" step="1"
                                           class="w-28 rounded-lg border border-border-default bg-surface-900 px-3 py-2 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                                    <span class="text-sm text-text-muted">€</span>
                                    <span class="text-xs text-text-muted">{{ $bands[$key][0] }}–{{ $bands[$key][1] }} €</span>
                                </div>
                                <p class="text-xs text-text-muted mt-1">{{ __('club.matchday.' . $key . '_hint') }}</p>
                            </div>
                        @endforeach
                        <button type="submit"
                                class="px-5 py-2.5 rounded-lg bg-accent-blue text-white text-sm font-semibold hover:brightness-110 transition">
                            {{ __('club.matchday.save') }}
                        </button>
                    </form>
                </x-section-card>
            </div>

            {{-- Projection for the next home match --}}
            <div>
                <x-section-card :title="__('club.matchday.projection_title')">
                    <div class="px-5 py-5">
                        @if($projection)
                            <p class="text-sm text-text-body">
                                <span class="font-semibold">{{ $game->team->name }}</span>
                                {{ __('club.matchday.vs') }}
                                <span class="font-semibold">{{ $projection['match']->awayTeam?->name ?? '—' }}</span>
                            </p>
                            <p class="text-xs text-text-muted mt-1">
                                {{ __('club.matchday.expected_crowd', [
                                    'attendance' => number_format($projection['attendance'], 0, ',', '.'),
                                    'capacity' => number_format($projection['capacity'], 0, ',', '.'),
                                ]) }}
                            </p>
                            <dl class="mt-4 space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-text-secondary">{{ __('finances.category_matchday_tickets') }}</dt>
                                    <dd class="font-semibold text-text-primary">{{ number_format($projection['revenue']['tickets'], 0, ',', '.') }} €</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-text-secondary">{{ __('finances.category_matchday_shirts') }}</dt>
                                    <dd class="font-semibold text-text-primary">{{ number_format($projection['revenue']['shirts'], 0, ',', '.') }} €</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-text-secondary">{{ __('finances.category_matchday_merch') }}</dt>
                                    <dd class="font-semibold text-text-primary">{{ number_format($projection['revenue']['merch'], 0, ',', '.') }} €</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-text-secondary">{{ __('finances.category_matchday_bars') }}</dt>
                                    <dd class="font-semibold text-text-primary">{{ number_format($projection['revenue']['bars'], 0, ',', '.') }} €</dd>
                                </div>
                                <div class="flex justify-between border-t border-border-default pt-2">
                                    <dt class="font-bold text-text-primary">{{ __('club.matchday.total') }}</dt>
                                    <dd class="font-bold text-accent-green">{{ number_format($projection['revenue']['total'], 0, ',', '.') }} €</dd>
                                </div>
                            </dl>
                            <p class="text-xs text-text-muted mt-3">{{ __('club.matchday.projection_note') }}</p>
                        @else
                            <p class="text-sm text-text-muted">{{ __('club.matchday.no_home_match') }}</p>
                        @endif
                    </div>
                </x-section-card>
            </div>
        </div>

        {{-- Recent matchday income --}}
        @if($recent->isNotEmpty())
            <div class="mt-4">
                <x-section-card :title="__('club.matchday.recent_title')">
                    <div class="px-5 py-5">
                        <ul class="divide-y divide-border-default text-sm">
                            @foreach($recent as $tx)
                                <li class="py-2.5 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-text-primary truncate">{{ $tx->category_label }}</div>
                                        <div class="text-xs text-text-muted truncate">{{ $tx->description }}</div>
                                    </div>
                                    <span class="shrink-0 font-bold text-accent-green">{{ $tx->signed_amount }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </x-section-card>
            </div>
        @endif

    </div>
</x-app-layout>
