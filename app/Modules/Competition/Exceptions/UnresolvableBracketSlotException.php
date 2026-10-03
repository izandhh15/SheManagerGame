<?php

namespace App\Modules\Competition\Exceptions;

use RuntimeException;

/**
 * Thrown when a knockout bracket slot cannot be resolved to a team.
 *
 * Previously the generator silently dropped the affected matchup (`if
 * ($homeTeamId && $awayTeamId)` with no else), shrinking the bracket
 * without a trace — a tournament could finish with no champion and no
 * error anywhere. An unresolvable slot means the upstream data is corrupt
 * (group standings missing, semifinal tie without a winner, bracket.json
 * referencing a slot that doesn't exist), so we fail loudly instead of
 * playing a broken tournament.
 */
class UnresolvableBracketSlotException extends RuntimeException
{
    public static function forSlot(
        string $competitionId,
        int $round,
        int|string $matchNumber,
        ?string $homeSlot,
        ?string $awaySlot,
    ): self {
        return new self(
            "Cannot resolve {$competitionId} round {$round} match {$matchNumber}: "
            . "slot '{$homeSlot}' vs '{$awaySlot}' produced no team. "
            . 'Check group standings, semifinal results, or bracket.json slot references.'
        );
    }
}
