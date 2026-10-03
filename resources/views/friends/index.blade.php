<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 pb-32">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">👥 {{ __('friends.title') }}</h2>
            <p class="text-sm text-text-secondary mt-1">{{ __('friends.subtitle') }}</p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-accent-green/30 bg-accent-green/10 px-4 py-3 text-sm text-accent-green">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-accent-red/30 bg-accent-red/10 px-4 py-3 text-sm text-accent-red">
                {{ session('error') }}
            </div>
        @endif

        {{-- Search / send request --}}
        <div class="rounded-xl border border-border-default bg-surface-800 p-5 mb-6">
            <form method="POST" action="{{ route('friends.request') }}" class="flex flex-col sm:flex-row gap-2">
                @csrf
                <input type="text" name="username" required maxlength="255"
                       placeholder="{{ __('friends.search_placeholder') }}"
                       class="flex-1 rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                <button type="submit"
                        class="rounded-lg bg-accent-blue px-4 py-2.5 text-sm font-bold uppercase tracking-wide text-white hover:brightness-110 transition">
                    {{ __('friends.search_button') }}
                </button>
            </form>
        </div>

        {{-- Pending received --}}
        <div class="mb-6">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-text-secondary mb-3">📥 {{ __('friends.pending_title') }} ({{ $pending->count() }})</h3>
            @if($pending->isEmpty())
                <p class="text-xs text-text-muted">{{ __('friends.no_pending') }}</p>
            @else
                <div class="space-y-2">
                    @foreach($pending as $req)
                        <div class="flex items-center gap-3 rounded-lg border border-border-default bg-surface-800 px-4 py-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-text-primary">{{ '@'.$req['username'] }}
                                    @if($req['is_federated'])
                                        <span class="ml-2 inline-flex items-center rounded-full bg-accent-purple/20 border border-accent-purple/40 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent-purple">🌐 {{ $req['peer_label'] }}</span>
                                    @endif
                                </p>
                                @if($req['is_federated'] && $req['club'])
                                    <p class="text-xs text-text-muted">{{ $req['club'] }}</p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('friends.accept', $req['id']) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-accent-green px-3 py-1.5 text-xs font-bold uppercase text-white hover:brightness-110">
                                    {{ __('friends.accept') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('friends.reject', $req['id']) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-surface-700 px-3 py-1.5 text-xs font-bold uppercase text-text-body hover:bg-surface-600">
                                    {{ __('friends.reject') }}
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Sent --}}
        @if($sent->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-text-secondary mb-3">📤 {{ __('friends.sent_title') }} ({{ $sent->count() }})</h3>
                <div class="space-y-2">
                    @foreach($sent as $req)
                        <div class="flex items-center gap-3 rounded-lg border border-border-default bg-surface-800 px-4 py-3">
                            <p class="flex-1 min-w-0 text-sm font-bold text-text-primary">{{ '@'.$req['username'] }}
                                @if($req['is_federated'])
                                    <span class="ml-2 inline-flex items-center rounded-full bg-accent-purple/20 border border-accent-purple/40 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent-purple">🌐 {{ $req['peer_label'] }}</span>
                                @endif
                            </p>
                            <form method="POST" action="{{ route('friends.remove', $req['id']) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-text-muted underline hover:text-text-body">{{ __('friends.remove') }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Friends list --}}
        <div class="mb-6">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-text-secondary mb-3">✅ {{ __('friends.friends_title') }} ({{ $friendships->count() }})</h3>
            @if($friendships->isEmpty())
                <div class="rounded-xl border border-border-default bg-surface-800 p-8 text-center">
                    <p class="text-4xl mb-3">👥</p>
                    <p class="text-sm text-text-muted">{{ __('friends.no_friends') }}</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($friendships as $row)
                        <div class="flex items-center gap-3 rounded-lg border border-border-default bg-surface-800 px-4 py-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-text-primary">{{ '@'.$row['username'] }}
                                    @if($row['is_federated'])
                                        <span class="ml-2 inline-flex items-center rounded-full bg-accent-purple/20 border border-accent-purple/40 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent-purple">🌐 {{ $row['peer_label'] }}</span>
                                    @endif
                                </p>
                                @if($row['is_federated'] && $row['club'])
                                    <p class="text-xs text-text-muted">{{ $row['club'] }}</p>
                                @endif
                            </div>
                            @if($row['careers_user_id'])
                                <a href="{{ route('friends.careers', $row['careers_user_id']) }}"
                                   class="rounded-lg bg-accent-blue px-3 py-1.5 text-xs font-bold uppercase text-white hover:brightness-110">
                                    {{ __('friends.view_careers') }}
                                </a>
                            @endif
                            <form method="POST" action="{{ route('friends.remove', $row['id']) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-text-muted underline hover:text-text-body">{{ __('friends.remove') }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Federation: players on the other platform --}}
        @if($federation['enabled'])
            <div class="mb-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-text-secondary mb-1">{{ __('friends.federation_title', ['platform' => $federation['peer_label']]) }}</h3>
                <p class="text-xs text-text-muted mb-3">{{ __('friends.federation_subtitle', ['platform' => $federation['peer_label']]) }}</p>
                @if($federation['error'])
                    <p class="text-xs text-text-muted">{{ __('friends.federation_unreachable', ['platform' => $federation['peer_label']]) }}</p>
                @elseif(empty($federation['players']))
                    <p class="text-xs text-text-muted">{{ __('friends.federation_empty', ['platform' => $federation['peer_label']]) }}</p>
                @else
                    <div class="space-y-2">
                        @foreach($federation['players'] as $player)
                            <div class="flex items-center gap-3 rounded-lg border border-accent-purple/30 bg-surface-800 px-4 py-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-text-primary">{{ '@'.$player['username'] }}
                                        <span class="ml-2 inline-flex items-center rounded-full bg-accent-purple/20 border border-accent-purple/40 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent-purple">🌐 {{ $federation['peer_label'] }}</span>
                                    </p>
                                    <p class="text-xs text-text-muted">{{ $player['club'] ?? __('friends.federation_unknown_club') }}@if($player['season']) · {{ $player['season'] }}@endif</p>
                                </div>
                                <form method="POST" action="{{ route('friends.federation.request') }}">
                                    @csrf
                                    <input type="hidden" name="username" value="{{ $player['username'] }}">
                                    <button type="submit" class="rounded-lg bg-accent-purple px-3 py-1.5 text-xs font-bold uppercase text-white hover:brightness-110">
                                        {{ __('friends.federation_add') }}
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <div class="mt-6">
            <a href="{{ route('dashboard') }}" class="text-sm text-accent-blue underline">← {{ __('game.back_to_dashboard') }}</a>
        </div>
    </div>
</x-app-layout>
