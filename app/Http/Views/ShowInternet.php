<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\SocialPost;
use Illuminate\Http\Request;

class ShowInternet
{
    public const TABS = ['all', 'prensa', 'fichajes', 'partidos', 'sedes', 'jugadoras', 'mister'];

    /** Map tab => contexts. */
    private function contextsFor(string $tab): ?array
    {
        return match ($tab) {
            'prensa' => ['journalist_welcome', 'journalist_preview', 'journalist_match', 'journalist_rumor'],
            'fichajes' => ['journalist_transfer', 'journalist_rumor'],
            'partidos' => ['journalist_preview', 'journalist_match', 'post_match'],
            'jugadoras' => ['player_reaction', 'player_lifestyle'],
            'mister' => ['manager_post'],
            // 'sedes' and 'all' handled separately.
            default => null,
        };
    }

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        if ((int) $game->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $tab = $request->query('tab', 'all');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'all';
        }

        $query = SocialPost::where('game_id', $game->id)
            ->whereNull('parent_post_id');

        if ($tab === 'sedes') {
            // Venue announcements: official posts tied to a match with a venue.
            $query->whereIn('context', ['national_official', 'club_official'])
                ->whereNotNull('match_id');
        } elseif ($contexts = $this->contextsFor($tab)) {
            $query->whereIn('context', $contexts);
        }

        $posts = $query->orderByDesc('created_at')->limit(100)->get();

        // Replies for thread display.
        $replies = SocialPost::where('game_id', $game->id)
            ->whereIn('parent_post_id', $posts->pluck('id'))
            ->orderBy('created_at')
            ->get()
            ->groupBy('parent_post_id');
        $posts->each(fn ($p) => $p->setRelation('replies', $replies->get($p->id, collect())));

        // Manager posting limits: 5/day.
        $today = now()->startOfDay();
        $postedToday = SocialPost::where('game_id', $game->id)
            ->where('context', 'manager_post')
            ->where('created_at', '>=', $today)
            ->count();

        return view('internet', [
            'game' => $game,
            'posts' => $posts,
            'tab' => $tab,
            'tabs' => self::TABS,
            'postedToday' => $postedToday,
            'dailyLimit' => 5,
        ]);
    }
}
