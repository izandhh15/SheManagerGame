@props(['narrative', 'game'])

@php
    $style = \App\Support\NarrativePresenter::style($narrative->category);
    $clickable = $style['route'] !== null;
    $tag = $clickable ? 'a' : 'div';
    $outlet = !empty($narrative->source)
        ? app(\App\Modules\Media\Services\MediaOutletService::class)->outletInfo($narrative->source)
        : null;
@endphp

<{{ $tag }}
    @if($clickable) href="{{ route($style['route'], $game->id) }}" @endif
    class="group flex items-start gap-3 px-5 py-3 {{ $clickable ? 'hover:bg-surface-700 transition-colors' : '' }}"
>
    <x-notification-icon :icon="$style['icon']" :icon-bg="$style['bg']" :icon-text="$style['text']" />

    <div class="flex-1">
        @if($outlet)
            <div class="mb-1 flex items-center gap-2">
                @if($outlet['logo'])
                    <img src="{{ $outlet['logo'] }}" alt="{{ $outlet['name'] }}" title="{{ $outlet['name'] }}"
                         class="h-5 w-auto max-w-[110px] object-contain rounded-sm bg-white/90 px-1 py-0.5"
                         loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';" />
                    <span style="display:none;background-color:{{ $outlet['color'] }}"
                          class="items-center rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">{{ $outlet['name'] }}</span>
                @else
                    <span style="background-color:{{ $outlet['color'] }}"
                          class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">{{ $outlet['name'] }}</span>
                @endif
            </div>
        @endif
        @if(!empty($narrative->headline))
            <p class="text-sm font-bold leading-snug text-text-primary">{{ $narrative->headline }}</p>
        @endif
        <p class="text-sm leading-relaxed text-text-secondary">{{ $narrative->text }}</p>
    </div>

    @if($clickable)
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-text-faint transition-colors group-hover:text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
    @endif
</{{ $tag }}>
