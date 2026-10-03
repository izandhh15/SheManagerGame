@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    @php
        $reasonLabels = [
            'final' => __('game.press_reason_label_final'),
            'derby' => __('game.press_reason_label_derby'),
            'european' => __('game.press_reason_label_european'),
            'rival' => __('game.press_reason_label_rival'),
        ];
        $answersByKey = [];
        foreach ($questions as $q) {
            foreach ($q['answers'] as $a) {
                $answersByKey[$q['key']][$a['key']] = $a;
            }
        }
    @endphp

    <div class="max-w-2xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                🎤 {{ __('game.press_conference_title') }}
            </h2>
            <p class="text-sm text-text-secondary mt-1">
                {{ $match->homeTeam?->name }} - {{ $match->awayTeam?->name }}
            </p>
            <div class="flex flex-wrap gap-2 mt-2">
                @foreach($reasons as $reason)
                    <span class="text-xs font-semibold uppercase tracking-wide px-2 py-1 rounded-full bg-accent-blue/15 text-accent-blue">
                        {{ $reasonLabels[$reason] ?? $reason }}
                    </span>
                @endforeach
            </div>
            <p class="text-xs text-text-faint mt-2">
                {{ __('game.press_prematch_hint') }}
            </p>
        </div>

        @if($record)
            <div class="p-6 rounded-xl bg-surface-800 border border-border-default">
                <p class="text-4xl mb-2 text-center">✅</p>
                <p class="text-text-primary font-semibold text-center">
                    {{ __('game.press_already_done') }}
                </p>

                <div class="mt-4 space-y-3">
                    @foreach($questions as $question)
                        @php $chosen = $record->answers[$question['key']] ?? null; @endphp
                        @if($chosen && isset($answersByKey[$question['key']][$chosen]))
                            @php $a = $answersByKey[$question['key']][$chosen]; @endphp
                            <div class="p-3 rounded-lg bg-surface-700/50 border border-border-default">
                                <p class="text-xs text-text-faint italic">«{{ $question['question'] }}»</p>
                                <p class="text-sm text-text-primary mt-1">🗣️ {{ $a['label'] }}</p>
                                <p class="text-xs mt-1 {{ ($a['morale'] + $a['confidence']) >= 0 ? 'text-accent-green' : 'text-accent-red' }}">
                                    {{ __('game.press_effect_morale') }} {{ $a['morale'] >= 0 ? '+' : '' }}{{ $a['morale'] }} ·
                                    {{ __('game.press_effect_confidence') }} {{ $a['confidence'] >= 0 ? '+' : '' }}{{ $a['confidence'] }}
                                </p>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="text-center mt-4">
                    <a href="{{ route('game.lineup', $game->id) }}" class="inline-block px-4 py-2 rounded-lg bg-accent-blue text-white text-sm font-semibold">
                        {{ __('game.press_back_to_lineup') }}
                    </a>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('game.pre-press.submit', [$game->id, $match->id]) }}" class="space-y-5">
                @csrf

                @foreach($questions as $question)
                    <div class="p-4 rounded-xl bg-surface-800 border border-border-default">
                        <p class="text-xs text-text-faint mb-1">
                            🎙️ {{ $question['by'] }} <span class="opacity-70">· {{ $question['outlet'] }}</span>
                        </p>
                        <p class="font-semibold text-text-primary mb-3">«{{ $question['question'] }}»</p>

                        <div class="space-y-2">
                            @foreach($question['answers'] as $answer)
                                <label class="block p-3 rounded-lg bg-surface-700/50 border border-border-default hover:border-accent-blue cursor-pointer transition-colors">
                                    <div class="flex items-start gap-3">
                                        <input type="radio" name="answers[{{ $question['key'] }}]" value="{{ $answer['key'] }}" class="mt-1" required>
                                        <div class="flex-1 text-sm text-text-primary">{{ $answer['label'] }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        @error('answers.' . $question['key'])
                            <p class="text-xs text-accent-red mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 px-4 py-3 rounded-xl bg-accent-blue text-white font-bold uppercase tracking-wide">
                        {{ __('game.press_answer_button') }}
                    </button>
                    <a href="{{ route('game.lineup', $game->id) }}" class="px-4 py-3 rounded-xl bg-surface-700 text-text-secondary font-semibold">
                        {{ __('game.press_skip') }}
                    </a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
