<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Public player directory for federation: username + current club + season.
 * No auth, no sensitive data (never emails). Paginated.
 */
class FederationDirectory
{
    public function __invoke(Request $request)
    {
        if (! config('federation.enabled')) {
            abort(404);
        }

        $perPage = (int) config('federation.directory_per_page', 15);

        $users = User::whereNotNull('username')
            ->orderBy('username')
            ->paginate($perPage);

        // Latest game per user in one query (ordered desc, first wins).
        $latest = [];
        $games = Game::with('team')
            ->whereIn('user_id', $users->pluck('id')->all())
            ->orderByDesc('updated_at')
            ->get();

        foreach ($games as $game) {
            if (! isset($latest[$game->user_id])) {
                $latest[$game->user_id] = $game;
            }
        }

        return response()->json([
            'data' => $users->getCollection()->map(function (User $user) use ($latest) {
                $game = $latest[$user->id] ?? null;

                return [
                    'username' => $user->username,
                    'club' => $game?->team?->name,
                    'season' => $game ? (string) $game->season : null,
                ];
            })->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}
