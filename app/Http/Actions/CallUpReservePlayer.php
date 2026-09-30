<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\ReserveTeam\Exceptions\FirstTeamSquadFullException;
use App\Modules\ReserveTeam\Exceptions\PlayerHasCommittedDealException;
use App\Modules\ReserveTeam\Services\ReserveTeamService;

class CallUpReservePlayer
{
    public function __construct(
        private readonly ReserveTeamService $reserveTeamService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->reserve_team_id === null, 404);

        $player = GamePlayer::where('id', $playerId)
            ->where('game_id', $gameId)
            ->where('team_id', $game->reserve_team_id)
            ->where('is_stand_in', false)
            ->firstOrFail();

        $playerName = $player->name ?? '';

        try {
            $this->reserveTeamService->callUpToFirstTeam($player, $game);
        } catch (PlayerHasCommittedDealException $e) {
            return redirect()->route('game.squad.reserve', $gameId)
                ->with('error', __('messages.reserve_move_blocked_by_deal', ['player' => $playerName]));
        } catch (FirstTeamSquadFullException $e) {
            return redirect()->route('game.squad.reserve', $gameId)
                ->with('error', __('messages.reserve_player_call_up_blocked_full'));
        } catch (\DomainException $e) {
            // Stand-in guard or not-registered cases.
            return redirect()->route('game.squad.reserve', $gameId)
                ->with('error', __('messages.reserve_player_call_up_blocked'));
        }

        return redirect()->route('game.squad.reserve', $gameId)
            ->with('success', __('messages.reserve_player_called_up', ['player' => $playerName]));
    }
}
