@props(['narrative', 'game'])

@php
    $style = \App\Support\NarrativePresenter::style($narrative->category);
    $clickable = $style['route'] !== null;
    $tag = $clickable ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if($clickable) href="{{ route($style['route'], $game->id) }}" @endif
    class="group flex items-start gap-3 px-5 py-3 {{ $clickable ? 'hover:bg-surface-700 transition-colors' : '' }}"
>
    <x-notification-icon :icon="$style['icon']" :icon-bg="$style['bg']" :icon-text="$style['text']" />

    <div class="flex-1">
        @if(!empty($narrative->source))
            <p class="text-[11px] font-bold uppercase tracking-wide text-text-faint">{{ $narrative->source }}</p>
        @endif
        <p class="text-sm leading-relaxed text-text-secondary">{{ $narrative->text }}</p>
    </div>

    @if($clickable)
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-text-faint transition-colors group-hover:text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
    @endif
</{{ $tag }}>
