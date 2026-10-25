{{--
    Botones "Entrar con Google / Discord". Solo se muestra cada botón
    si su proveedor está configurado (services.google/discord.client_id):
    sin variables de entorno, esta vista no renderiza nada y el login
    y el registro quedan exactamente como antes.
--}}
@if(config('services.google.client_id') || config('services.discord.client_id'))
    <div class="mt-6">
        <p class="text-center text-sm text-text-secondary">{{ __('auth.social_or') }}</p>
        <div class="mt-3 space-y-2">
            @if(config('services.google.client_id'))
                <a href="{{ route('social.redirect', ['provider' => 'google']) }}"
                   class="flex w-full items-center justify-center gap-2 rounded-lg border border-border-strong bg-surface-800 px-4 py-2.5 text-sm font-semibold text-text-primary hover:bg-surface-700 transition-colors">
                    <span class="font-bold">G</span> {{ __('auth.social_google') }}
                </a>
            @endif
            @if(config('services.discord.client_id'))
                <a href="{{ route('social.redirect', ['provider' => 'discord']) }}"
                   class="flex w-full items-center justify-center gap-2 rounded-lg border border-border-strong bg-[#5865F2] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition-opacity">
                    {{ __('auth.social_discord') }}
                </a>
            @endif
        </div>
    </div>
@endif
