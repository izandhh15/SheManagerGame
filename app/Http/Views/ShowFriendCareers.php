<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\User;
use App\Modules\Social\Services\CareerHistoryService;
use App\Modules\Social\Services\FriendshipService;
use Illuminate\Http\Request;

class ShowFriendCareers
{
    public function __construct(
        private readonly FriendshipService $friendships,
        private readonly CareerHistoryService $history,
    ) {}

    public function __invoke(Request $request, string $userId)
    {
        $viewer = $request->user();

        // The id is compared against a bigint column: any non-numeric value
        // would make Postgres throw a QueryException (22P02) before the
        // privacy gate could answer 403/404.
        if (! ctype_digit($userId)) {
            abort(404);
        }

        // Privacy gate: only mutual friends can see careers.
        if (! $this->friendships->areFriends($viewer->id, $userId)) {
            abort(403, __('friends.not_friends'));
        }

        $friend = User::findOrFail($userId);

        // Active saves (same shape as the dashboard listing).
        $games = Game::with(['team', 'competition'])
            ->where('user_id', $friend->id)
            ->whereNull('deleting_at')
            ->orderByDesc('updated_at')
            ->get();

        return view('friends.careers', [
            'friend' => $friend,
            'games' => $games,
            'history' => $this->history->historyFor($friend),
        ]);
    }
}
