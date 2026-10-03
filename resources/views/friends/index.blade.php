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
                                <p class="text-sm font-bold text-text-primary">@{{ $req->user->username }}</p>
                            </div>
                            <form method="POST" action="{{ route('friends.accept', $req->id) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-accent-green px-3 py-1.5 text-xs font-bold uppercase text-white hover:brightness-110">
                                    {{ __('friends.accept') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('friends.reject', $req->id) }}">
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
                            <p class="flex-1 min-w-0 text-sm font-bold text-text-primary">@{{ $req->friend->username }}</p>
                            <form method="POST" action="{{ route('friends.remove', $req->id) }}">
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
                                <p class="text-sm font-bold text-text-primary">@{{ $row['other']->username }}</p>
                            </div>
                            <a href="{{ route('friends.careers', $row['other']->id) }}"
                               class="rounded-lg bg-accent-blue px-3 py-1.5 text-xs font-bold uppercase text-white hover:brightness-110">
                                {{ __('friends.view_careers') }}
                            </a>
                            <form method="POST" action="{{ route('friends.remove', $row['friendship']->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-text-muted underline hover:text-text-body">{{ __('friends.remove') }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mt-6">
            <a href="{{ route('dashboard') }}" class="text-sm text-accent-blue underline">← {{ __('game.back_to_dashboard') }}</a>
        </div>
    </div>
</x-app-layout>
