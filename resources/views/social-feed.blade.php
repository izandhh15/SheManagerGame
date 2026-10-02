@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-4 flex items-center justify-between gap-4">
            <div>
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    🐦 {{ __('game.social_title', [], 'es') === 'game.social_title' ? 'Red Social' : __('game.social_title') }}
                </h2>
                <p class="text-sm text-text-secondary mt-1">
                    {{ __('game.social_subtitle', [], 'es') === 'game.social_subtitle' ? 'Lo que dice la afición de ti...' : __('game.social_subtitle') }}
                </p>
            </div>
            <div class="text-right shrink-0">
                <div class="text-xs uppercase tracking-wide text-text-faint">{{ __('game.board_confidence', [], 'es') === 'game.board_confidence' ? 'Confianza directiva' : __('game.board_confidence') }}</div>
                <div class="text-2xl font-bold {{ $boardConfidence >= 50 ? 'text-green-500' : ($boardConfidence >= 30 ? 'text-yellow-500' : 'text-red-500') }}">
                    {{ $boardConfidence }}%
                </div>
            </div>
        </div>

        @if(session('press_done'))
            <div class="mb-4 p-3 rounded-lg bg-accent-blue/10 border border-accent-blue/30 text-sm text-text-primary">
                {{ __('game.press_published', [], 'es') === 'game.press_published' ? 'Tus declaraciones ya están circulando por la red...' : __('game.press_published') }}
            </div>
        @endif

        @if(session('reply_done'))
            <div class="mb-4 p-3 rounded-lg bg-accent-blue/10 border border-accent-blue/30 text-sm text-text-primary">
                Tu respuesta ya está circulando por la red...
            </div>
        @endif

        @if($boardConfidence < 30)
            <div class="mb-4 p-4 rounded-lg bg-red-500/10 border border-red-500/40">
                <p class="font-bold text-red-500">⚠️ {{ __('game.board_warning', [], 'es') === 'game.board_warning' ? 'La directiva está perdiendo la paciencia. ¡Cuidado con lo que dices!' : __('game.board_warning') }}</p>
            </div>
        @endif

        <div class="flex gap-4 mb-4 text-sm">
            <span class="text-green-500 font-semibold">👍 {{ $positiveCount }}</span>
            <span class="text-red-500 font-semibold">👎 {{ $negativeCount }}</span>
        </div>

        <div class="space-y-3">
            @forelse($posts as $post)
                <div class="p-4 rounded-xl bg-surface-800 border {{ $post->sentiment < 0 ? 'border-red-500/30' : ($post->sentiment > 0 ? 'border-green-500/30' : 'border-border-default') }}">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-10 h-10 rounded-full {{ $post->journalist_id ? 'bg-gradient-to-br from-sky-500 to-blue-700' : 'bg-gradient-to-br from-purple-500 to-pink-500' }} flex items-center justify-center text-white font-bold">
                            {{ substr($post->author_name, 0, 1) }}
                        </div>
                        <div class="flex-1">
                            <div class="font-semibold text-text-primary text-sm">
                                {{ $post->author_name }}
                                @if($post->journalist_id)
                                    <span class="text-sky-400" title="Cuenta verificada de la redacción">✓</span>
                                @endif
                            </div>
                            <div class="text-text-faint text-xs">{{ $post->author_handle }}</div>
                        </div>
                        @if($post->journalist_id)
                            <span class="text-[10px] font-bold uppercase tracking-wide text-sky-400 bg-sky-500/10 border border-sky-500/30 rounded-full px-2 py-0.5">🎙️ Periodista</span>
                        @elseif($post->sentiment > 0)
                            <span class="text-green-500 text-lg">👍</span>
                        @elseif($post->sentiment < 0)
                            <span class="text-red-500 text-lg">👎</span>
                        @endif
                    </div>
                    <p class="text-text-primary text-sm leading-relaxed">{{ $post->text }}</p>
                    <div class="flex items-center gap-4 mt-2 text-xs text-text-faint">
                        <form method="POST" action="{{ route('game.social.like', [$game->id, $post->id]) }}" class="inline">
                            @csrf
                            <button type="submit" class="hover:scale-110 transition-transform" title="Me gusta">
                                {{ in_array($post->id, $likedPosts ?? []) ? '❤️' : '🤍' }} {{ $post->likes }}
                            </button>
                        </form>
                        <span>{{ $post->created_at->diffForHumans() }}</span>
                        @if($post->context === 'sacked')
                            <span class="font-bold text-red-500 uppercase">Destitución</span>
                        @elseif($post->context === 'board_warning')
                            <span class="font-bold text-yellow-500 uppercase">Rumor</span>
                        @elseif($post->journalist_id)
                            <span class="font-bold text-sky-400 uppercase">Noticia</span>
                        @endif
                    </div>

                    {{-- Manager reply to haters --}}
                    @if(!empty($post->manager_reply_text))
                        <div class="mt-3 ml-6 p-3 rounded-lg bg-accent-blue/10 border border-accent-blue/30">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">TÚ</div>
                                <span class="text-xs font-semibold text-text-primary">Tu respuesta</span>
                            </div>
                            <p class="text-text-primary text-sm">{{ $post->manager_reply_text }}</p>
                        </div>
                    @elseif($post->sentiment < 0)
                        <div class="mt-3">
                            <button type="button"
                                    onclick="document.getElementById('reply-form-{{ $post->id }}').classList.toggle('hidden')"
                                    class="text-xs font-semibold text-accent-blue hover:underline">
                                💬 Responder al hater
                            </button>
                            <form id="reply-form-{{ $post->id }}" method="POST"
                                  action="{{ route('game.social.reply', [$game->id, $post->id]) }}"
                                  class="hidden mt-2 p-3 rounded-lg bg-surface-700/50 border border-border-default space-y-2">
                                @csrf
                                @foreach($haterReplies as $reply)
                                    <label class="flex items-start gap-2 text-sm text-text-primary cursor-pointer hover:bg-surface-700 rounded px-2 py-1">
                                        <input type="radio" name="reply_key" value="{{ $reply['key'] }}" class="mt-1" {{ $loop->first ? 'checked' : '' }}>
                                        <span>{{ $reply['label'] }}</span>
                                    </label>
                                @endforeach
                                <button type="submit"
                                        class="mt-1 px-3 py-1.5 rounded-lg bg-accent-blue text-white text-xs font-bold hover:opacity-90">
                                    Publicar respuesta
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center rounded-xl bg-surface-800 border border-border-default">
                    <p class="text-4xl mb-2">🐦</p>
                    <p class="text-text-secondary">
                        {{ __('game.social_empty', [], 'es') === 'game.social_empty' ? 'Aún no hay actividad. Juega partidos y atiende a la prensa para que la afición hable de ti.' : __('game.social_empty') }}
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
