@props(['competition', 'competitionId' => null])

@php
    $id = $competitionId ?? $competition?->id ?? null;
    $url = \App\Support\CompetitionLogos::url($id);
    $name = $competition?->name ? __($competition->name) : $id;
@endphp

@if($url)
<img src="{{ $url }}" {{ $attributes->merge(['alt' => $name, 'class' => 'object-contain']) }}>
@endif
