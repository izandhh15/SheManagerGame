@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GameMatch $match */
    /** @var App\Models\Team $opponent */
@endphp

<x-game-nextgen-layout :game="$game ?? null">
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-12">
        @include('partials.opponent-analysis-content')
    </div>
</x-game-nextgen-layout>
