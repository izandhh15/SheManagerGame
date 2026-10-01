<?php
// Temporary endpoint for patching games after Arratia/Murcia updates
if (($_GET['t'] ?? '') !== 'temp-reseed-01102026') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

header('Content-Type: application/json');
$out = [];

try {
    $games = DB::table('games as g')
        ->join('teams as t', 't.id', '=', 'g.team_id')
        ->where('t.type', '!=', 'national')
        ->select('g.id')->get();
    $gameIds = $games->pluck('id')->all();
    $out['games_count'] = count($gameIds);

    if (count($gameIds) > 0) {
        // INSERT masivo: plantillas nuevas (IDs 98xxxxx) que falten en cada partida
        $addedPlayers = DB::insert(<<<SQL
            INSERT INTO game_players (
                id, game_id, player_id,
                transfermarkt_id, sofascore_id, fc26_id, name, date_of_birth, nationality, height, foot,
                team_id, number, position, secondary_positions,
                market_value, market_value_cents, contract_until, annual_wage, release_clause, durability,
                overall_score,
                potential, potential_low, potential_high, tier
            )
            SELECT
                gen_random_uuid(), g.id, t.player_id,
                t.transfermarkt_id, t.sofascore_id, t.fc26_id, t.name, t.date_of_birth, t.nationality, t.height, t.foot,
                t.team_id,
                COALESCE((SELECT MAX(gp2.number) FROM game_players gp2 WHERE gp2.game_id = g.id AND gp2.team_id = t.team_id), 0)
                  + ROW_NUMBER() OVER (PARTITION BY g.id, t.team_id ORDER BY t.player_id),
                t.position, t.secondary_positions,
                t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.release_clause, t.durability,
                t.overall_score,
                t.potential, t.potential_low, t.potential_high, t.tier
            FROM games g
            CROSS JOIN game_player_templates t
            WHERE t.season = '2026'
              AND t.transfermarkt_id LIKE '98%'
              AND NOT EXISTS (
                  SELECT 1 FROM game_players gp
                  WHERE gp.game_id = g.id AND gp.player_id = t.player_id
              )
            ON CONFLICT (game_id, player_id) DO NOTHING
        SQL);
        $out['inserted_rows'] = $addedPlayers;

        $addedState = DB::insert(<<<'SQL'
            INSERT INTO game_player_match_state (game_player_id, game_id, fitness, morale)
            SELECT gp.id, gp.game_id, t.fitness, t.morale
            FROM game_players gp
            JOIN game_player_templates t
              ON t.player_id = gp.player_id
             AND t.team_id = gp.team_id
             AND t.season = '2026'
             AND t.transfermarkt_id LIKE '98%'
            WHERE NOT EXISTS (
                SELECT 1 FROM game_player_match_state s WHERE s.game_player_id = gp.id
            )
            ON CONFLICT (game_player_id) DO NOTHING
        SQL);
        $out['inserted_state_rows'] = $addedState;
    }
    $out['games_patched'] = 'bulk';
} catch (Throwable $e) {
    $out['error'] = get_class($e).': '.$e->getMessage();
}

echo json_encode($out);
