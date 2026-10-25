<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Entrar con Google o Discord, sin registrarse con contraseña.
 *
 * OAuth2 manual (sin paquetes nuevos): el flujo es el mismo para los dos
 * proveedores y solo cambia el endpoint y el campo del nombre. Los
 * botones solo se muestran cuando el proveedor está configurado
 * (ver auth.social-buttons), así que desplegar este código sin las
 * variables de entorno no cambia nada para los jugadores actuales.
 *
 * Seguridad: el parámetro `state` se guarda en sesión y se verifica en
 * el callback. Los tokens se guardan cifrados (encrypt()).
 *
 * Neon pooler: NADA de transacciones ni locks aquí. Crear usuario y
 * crear cuenta social son dos INSERT sueltos, como en el registro
 * normal (un INSERT...RETURNING seguido de otra query en la misma
 * transacción da 25P02 y tumba la petición).
 */
class SocialLoginController extends Controller
{
    private const PROVIDERS = [
        'google' => [
            'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'userinfo' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
        ],
        'discord' => [
            'authorize' => 'https://discord.com/oauth2/authorize',
            'token' => 'https://discord.com/api/oauth2/token',
            'userinfo' => 'https://discord.com/api/users/@me',
            'scope' => 'identify email',
        ],
    ];

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $config = $this->providerConfig($provider);
        if ($config === null) {
            return redirect()->route('login')
                ->withErrors(['email' => __('auth.social_not_configured')]);
        }

        $state = Str::random(40);
        $request->session()->put('social_state', $state);
        $request->session()->put('social_provider', $provider);

        $params = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => route('social.callback', ['provider' => $provider]),
            'response_type' => 'code',
            'scope' => $config['scope'],
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'select_account',
        ]);

        return redirect()->away($config['authorize'] . '?' . $params);
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $config = $this->providerConfig($provider);
        if ($config === null) {
            return redirect()->route('login')
                ->withErrors(['email' => __('auth.social_not_configured')]);
        }

        // El usuario denegó el permiso en el proveedor, o el state no
        // coincide (posible CSRF): fuera, sin crear ni enlazar nada.
        if ($request->filled('error')
            || $request->input('state') !== $request->session()->pull('social_state')
            || $request->session()->pull('social_provider') !== $provider
            || ! $request->filled('code')) {
            return redirect()->route('login')
                ->withErrors(['email' => __('auth.social_failed')]);
        }

        $tokenResponse = Http::asForm()->post($config['token'], [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('social.callback', ['provider' => $provider]),
            'code' => $request->input('code'),
        ]);

        if (! $tokenResponse->successful()) {
            return redirect()->route('login')
                ->withErrors(['email' => __('auth.social_failed')]);
        }

        $token = $tokenResponse->json('access_token');
        $userResponse = Http::withToken($token)->get($config['userinfo']);
        if (! $userResponse->successful()) {
            return redirect()->route('login')
                ->withErrors(['email' => __('auth.social_failed')]);
        }

        $remote = $this->normalizeUser($provider, $userResponse->json());
        if (empty($remote['email'])) {
            return redirect()->route('login')
                ->withErrors(['email' => __('auth.social_no_email')]);
        }

        $social = SocialAccount::where('provider', $provider)
            ->where('provider_id', $remote['id'])
            ->first();

        // Caso 1: esta cuenta social ya existe → entrar con su usuario.
        if ($social !== null) {
            Auth::login($social->user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        }

        // Caso 2: el jugador ya tiene sesión → enlazar el proveedor a
        // su cuenta actual (si otro usuario no la tiene ya, cosa que
        // el caso 1 habría resuelto antes de llegar aquí).
        if (Auth::check()) {
            $this->createSocialAccount(Auth::user(), $provider, $remote, $token, $tokenResponse->json('refresh_token'));

            return redirect()->route('profile.edit')
                ->with('status', __('auth.social_linked'));
        }

        // Caso 3: mismo email que una cuenta existente → enlazar y entrar.
        $user = User::where('email', $remote['email'])->first();
        if ($user === null) {
            $user = $this->createUser($remote);
        }

        $this->createSocialAccount($user, $provider, $remote, $token, $tokenResponse->json('refresh_token'));

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** Config del proveedor o null si no está configurado. */
    private function providerConfig(string $provider): ?array
    {
        if (! isset(self::PROVIDERS[$provider])) {
            return null;
        }

        $clientId = config("services.{$provider}.client_id");
        $clientSecret = config("services.{$provider}.client_secret");
        if (empty($clientId) || empty($clientSecret)) {
            return null;
        }

        return array_merge(self::PROVIDERS[$provider], [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
    }

    /** Normaliza el perfil remoto a id/email/name/avatar. */
    private function normalizeUser(string $provider, array $remote): array
    {
        if ($provider === 'discord') {
            $name = $remote['global_name'] ?? $remote['username'] ?? null;
            $avatar = ! empty($remote['avatar'])
                ? "https://cdn.discordapp.com/avatars/{$remote['id']}/{$remote['avatar']}.png"
                : null;

            return [
                'id' => (string) $remote['id'],
                'email' => $remote['email'] ?? null,
                'name' => $name,
                'avatar' => $avatar,
            ];
        }

        return [
            'id' => (string) $remote['sub'],
            'email' => $remote['email'] ?? null,
            'name' => $remote['name'] ?? null,
            'avatar' => $remote['picture'] ?? null,
        ];
    }

    /** Un solo INSERT: crear usuario con acceso completo, como el registro abierto. */
    private function createUser(array $remote): User
    {
        $user = new User([
            'name' => $remote['name'] ?? $remote['email'],
            'email' => $remote['email'],
            'username' => $this->uniqueUsername($remote['name'] ?? $remote['email']),
            'password' => Hash::make(Str::random(40)),
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'has_career_access' => true,
            'has_tournament_access' => true,
        ]);
        $user->save();

        return $user;
    }

    /** Un solo INSERT: enlazar la cuenta social al usuario. */
    private function createSocialAccount(User $user, string $provider, array $remote, ?string $token, ?string $refreshToken): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $remote['id'],
            'provider_email' => $remote['email'],
            'avatar' => $remote['avatar'],
            'token' => $token !== null ? encrypt($token) : null,
            'refresh_token' => $refreshToken !== null ? encrypt($refreshToken) : null,
        ]);
    }

    /** Nombre de usuario único derivado del nombre (lo usan los amigos). */
    private function uniqueUsername(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'jugador';
        }
        $candidate = $base;
        $i = 2;
        while (User::where('username', $candidate)->exists()) {
            $candidate = "{$base}-{$i}";
            $i++;
        }

        return $candidate;
    }
}
