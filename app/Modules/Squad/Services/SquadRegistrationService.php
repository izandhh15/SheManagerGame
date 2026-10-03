<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SquadRegistrationService
{
    /**
     * Validate and save squad registration assignments.
     *
     * @param  Collection<int, array{player_id: string, number: int}>  $assignments
     * @throws \App\Modules\Squad\Exceptions\RegistrationException
     */
    public function save(Game $game, Collection $assignments): void
    {
        $this->validateNoDuplicateNumbers($assignments);
        $this->validateNumberRange($assignments);
        $this->validateFirstTeamLimit($assignments);
        $this->validatePlayersExist($game, $assignments);
        $this->validateAcademyAgeLimit($game, $assignments);

        DB::transaction(function () use ($game, $assignments) {
            GamePlayer::where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->update(['number' => null]);

            foreach ($assignments as $assignment) {
                GamePlayer::where('game_id', $game->id)
                    ->where('team_id', $game->team_id)
                    ->where('id', $assignment['player_id'])
                    ->update(['number' => $assignment['number']]);
            }
        });
    }

    private function validateFirstTeamLimit(Collection $assignments): void
    {
        $firstTeamCount = $assignments->filter(fn ($a) => $a['number'] >= 1 && $a['number'] <= 25)->count();

        if ($firstTeamCount > 25) {
            throw RegistrationException::tooManyFirstTeam();
        }
    }

    private function validateNoDuplicateNumbers(Collection $assignments): void
    {
        $numbers = $assignments->pluck('number');

        if ($numbers->count() !== $numbers->unique()->count()) {
            throw RegistrationException::duplicateNumber();
        }
    }

    /**
     * Shirt numbers must be in the 1–99 range (a direct POST could
     * otherwise persist 0, 999 or non-numeric values).
     */
    private function validateNumberRange(Collection $assignments): void
    {
        foreach ($assignments as $assignment) {
            $number = $assignment['number'] ?? null;
            if (!is_int($number) && !(is_string($number) && ctype_digit($number))) {
                throw RegistrationException::invalidNumber();
            }
            $number = (int) $number;
            if ($number < 1 || $number > 99) {
                throw RegistrationException::invalidNumber();
            }
        }
    }

    private function validatePlayersExist(Game $game, Collection $assignments): void
    {
        $playerIds = $assignments->pluck('player_id');

        $validCount = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereIn('id', $playerIds)
            ->count();

        if ($validCount !== $playerIds->count()) {
            throw RegistrationException::invalidPlayers();
        }
    }

    private function validateAcademyAgeLimit(Game $game, Collection $assignments): void
    {
        $academyAssignments = $assignments->filter(fn ($a) => $a['number'] > 25);

        if ($academyAssignments->isEmpty()) {
            return;
        }

        $academyPlayerIds = $academyAssignments->pluck('player_id');

        $overageCount = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereIn('id', $academyPlayerIds)
            ->where('date_of_birth', '<', $game->getU23BirthCutoff())
            ->count();

        if ($overageCount > 0) {
            throw RegistrationException::academyAgeLimit();
        }
    }
}
