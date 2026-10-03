<?php

namespace App\Modules\Social\Services;

use App\Models\Friendship;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Federation between two platform instances (e.g. Wasmer and Vercel), each
 * with its own database, so players can see each other and become friends
 * across instances.
 *
 * Wire format for signed requests:
 *   POST {peer}/api/federation/<endpoint> with JSON body $payload and headers
 *     X-Federation-Timestamp: unix timestamp (string)
 *     X-Federation-Signature: HMAC-SHA256(timestamp + "\n" + raw_body, secret)
 * Requests with a timestamp older than federation.timestamp_tolerance
 * seconds are rejected.
 */
class FederationService
{
    public function enabled(): bool
    {
        return (bool) config('federation.enabled');
    }

    public function peerUrl(): string
    {
        return (string) config('federation.peer_url');
    }

    public function localBaseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Short human label for the peer instance (Wasmer / Vercel / host).
     */
    public function peerLabel(): string
    {
        return self::labelForUrl($this->peerUrl());
    }

    public function localLabel(): string
    {
        return self::labelForUrl($this->localBaseUrl());
    }

    public static function labelForUrl(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (str_contains($host, 'wasmer')) {
            return 'Wasmer';
        }

        if (str_contains($host, 'vercel')) {
            return 'Vercel';
        }

        return $host !== '' ? $host : 'otra plataforma';
    }

    // ------------------------------------------------------------------
    // Signing
    // ------------------------------------------------------------------

    public function sign(string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp."\n".$body, (string) config('federation.secret'));
    }

    /**
     * Validate the federation signature headers of an incoming request.
     */
    public function verifyRequest(Request $request): bool
    {
        $timestamp = (string) $request->header('X-Federation-Timestamp', '');
        $signature = (string) $request->header('X-Federation-Signature', '');

        if ($timestamp === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > (int) config('federation.timestamp_tolerance', 300)) {
            return false;
        }

        $expected = $this->sign($timestamp, $request->getContent());

        return hash_equals($expected, $signature);
    }

    // ------------------------------------------------------------------
    // Outgoing signed requests
    // ------------------------------------------------------------------

    /**
     * POST a signed JSON payload to the peer instance.
     *
     * @return array{ok: bool, status?: int, json?: mixed, error?: string}
     */
    public function notifyPeer(string $path, array $payload, ?string $baseUrl = null): array
    {
        $base = rtrim($baseUrl ?? $this->peerUrl(), '/');
        $timestamp = (string) time();
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Federation-Timestamp' => $timestamp,
                    'X-Federation-Signature' => $this->sign($timestamp, $body),
                ])
                ->withBody($body, 'application/json')
                ->post($base.$path);

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'json' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Federation peer unreachable', ['path' => $path, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Peer directory (consumer side, cached 15 min)
    // ------------------------------------------------------------------

    /**
     * @return array{data: list<array{username: string, club: ?string, season: ?string}>, meta: array, error: bool}
     */
    public function peerDirectory(int $page = 1): array
    {
        $page = max(1, $page);
        $key = "federation:directory:page:{$page}";

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->fetchPeerDirectory($page);

        if ($result['error']) {
            // Cache failures briefly: a down peer shouldn't slow down every
            // friends page load, but we want to retry soon.
            Cache::put($key, $result, 60);
        } else {
            Cache::put($key, $result, (int) config('federation.directory_ttl', 900));
        }

        return $result;
    }

    private function fetchPeerDirectory(int $page): array
    {
        try {
            $response = Http::timeout(10)->get(
                $this->peerUrl().'/api/federation/players',
                ['page' => $page]
            );

            if (! $response->successful()) {
                return ['data' => [], 'meta' => [], 'error' => true];
            }

            $json = $response->json();

            return [
                'data' => $json['data'] ?? [],
                'meta' => $json['meta'] ?? [],
                'error' => false,
            ];
        } catch (\Throwable $e) {
            Log::warning('Federation directory fetch failed', ['error' => $e->getMessage()]);

            return ['data' => [], 'meta' => [], 'error' => true];
        }
    }

    // ------------------------------------------------------------------
    // Current club of a local user (for the public directory)
    // ------------------------------------------------------------------

    /**
     * @return array{club: string, season: string}|null
     */
    public function currentClubFor(User $user): ?array
    {
        $game = Game::with('team')
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->first();

        if (! $game || ! $game->team) {
            return null;
        }

        return ['club' => $game->team->name, 'season' => (string) $game->season];
    }

    // ------------------------------------------------------------------
    // Outgoing friend request (local user -> peer username)
    // ------------------------------------------------------------------

    /**
     * @return array{ok: bool, message: string, friendship?: Friendship}
     */
    public function sendRequest(User $user, string $remoteUsername): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'message' => __('friends.federation_disabled')];
        }

        $remoteUsername = trim($remoteUsername);

        if ($remoteUsername === '') {
            return ['ok' => false, 'message' => __('friends.username_required')];
        }

        // A local user with that name? Use the normal flow instead.
        if (User::where('username', $remoteUsername)->exists()) {
            return ['ok' => false, 'message' => __('friends.federation_use_local')];
        }

        $existing = Friendship::where('user_id', $user->id)
            ->where('is_federated', true)
            ->where('remote_username', $remoteUsername)
            ->where('remote_instance', $this->peerUrl())
            ->first();

        if ($existing) {
            return ['ok' => false, 'message' => __('friends.already_exists')];
        }

        $club = $this->currentClubFor($user);

        $friendship = Friendship::create([
            'user_id' => $user->id,
            'friend_id' => null,
            'status' => Friendship::STATUS_PENDING,
            'is_federated' => true,
            'is_remote_sender' => false,
            'remote_username' => $remoteUsername,
            'remote_club' => null,
            'remote_instance' => $this->peerUrl(),
        ]);

        $notify = $this->notifyPeer('/api/federation/friend-request', [
            'from_username' => $user->username,
            'from_user_id' => $user->id,
            'from_club' => $club['club'] ?? null,
            'from_request_uuid' => $friendship->id,
            'from_instance' => $this->localBaseUrl(),
            'to_username' => $remoteUsername,
        ]);

        if (! $notify['ok']) {
            $friendship->delete();

            $notFound = ($notify['json']['error'] ?? null) === 'user_not_found'
                || ($notify['status'] ?? 0) === 404;

            return [
                'ok' => false,
                'message' => $notFound
                    ? __('friends.federation_user_not_found')
                    : __('friends.federation_unreachable'),
            ];
        }

        // Correlate with the peer's row so later accept/reject/remove can
        // be routed both ways.
        if (is_string($notify['json']['request_uuid'] ?? null)) {
            $friendship->update(['remote_request_uuid' => $notify['json']['request_uuid']]);
        }

        return ['ok' => true, 'message' => __('friends.request_sent'), 'friendship' => $friendship];
    }

    // ------------------------------------------------------------------
    // Incoming requests (called by the API actions after verification)
    // ------------------------------------------------------------------

    /**
     * @return array{ok: bool, request_uuid?: string, error?: string}
     */
    public function handleIncomingRequest(array $data): array
    {
        $to = User::where('username', $data['to_username'])->first();

        if (! $to) {
            return ['ok' => false, 'error' => 'user_not_found'];
        }

        // Idempotent: a retry must not duplicate the row.
        $existing = Friendship::where('user_id', $to->id)
            ->where('is_federated', true)
            ->where('remote_username', $data['from_username'])
            ->where('remote_instance', $data['from_instance'])
            ->first();

        if ($existing) {
            return ['ok' => true, 'request_uuid' => $existing->id];
        }

        $row = Friendship::create([
            'user_id' => $to->id,
            'friend_id' => null,
            'status' => Friendship::STATUS_PENDING,
            'is_federated' => true,
            'is_remote_sender' => true,
            'remote_user_id' => $data['from_user_id'] ?? null,
            'remote_username' => $data['from_username'],
            'remote_club' => $data['from_club'] ?? null,
            'remote_instance' => $data['from_instance'],
            'remote_request_uuid' => $data['from_request_uuid'],
        ]);

        return ['ok' => true, 'request_uuid' => $row->id];
    }

    public function handlePeerAccept(string $requestUuid): bool
    {
        $row = Friendship::where('id', $requestUuid)
            ->where('is_federated', true)
            ->where('status', Friendship::STATUS_PENDING)
            ->first();

        if (! $row) {
            return false;
        }

        $row->update(['status' => Friendship::STATUS_ACCEPTED]);

        return true;
    }

    public function handlePeerReject(string $requestUuid): bool
    {
        $row = Friendship::where('id', $requestUuid)
            ->where('is_federated', true)
            ->where('status', Friendship::STATUS_PENDING)
            ->first();

        if (! $row) {
            return false;
        }

        $row->delete();

        return true;
    }

    public function handlePeerRemove(string $requestUuid): bool
    {
        $row = Friendship::where('id', $requestUuid)
            ->where('is_federated', true)
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->first();

        if (! $row) {
            return false;
        }

        $row->delete();

        return true;
    }

    // ------------------------------------------------------------------
    // Propagation to the peer (called after a local accept/reject/remove)
    // ------------------------------------------------------------------

    public function propagateAccept(Friendship $friendship): void
    {
        if (! $this->shouldPropagate($friendship)) {
            return;
        }

        $this->notifyPeerInstance(
            $friendship->remote_instance,
            '/api/federation/friend-accept',
            ['request_uuid' => $friendship->remote_request_uuid]
        );
    }

    public function propagateReject(Friendship $friendship): void
    {
        if (! $this->shouldPropagate($friendship)) {
            return;
        }

        $this->notifyPeerInstance(
            $friendship->remote_instance,
            '/api/federation/friend-reject',
            ['request_uuid' => $friendship->remote_request_uuid]
        );
    }

    public function propagateRemove(Friendship $friendship): void
    {
        if (! $this->shouldPropagate($friendship)) {
            return;
        }

        $this->notifyPeerInstance(
            $friendship->remote_instance,
            '/api/federation/friend-remove',
            ['request_uuid' => $friendship->remote_request_uuid]
        );
    }

    private function shouldPropagate(Friendship $friendship): bool
    {
        return $this->enabled()
            && $friendship->isFederated()
            && is_string($friendship->remote_instance)
            && $friendship->remote_instance !== ''
            && is_string($friendship->remote_request_uuid)
            && $friendship->remote_request_uuid !== '';
    }

    private function notifyPeerInstance(?string $baseUrl, string $path, array $payload): void
    {
        if (! $baseUrl) {
            return;
        }

        // Fire and forget: the local state already changed; a failed
        // notification is logged by notifyPeer().
        $this->notifyPeer($path, $payload, $baseUrl);
    }
}
