<?php
// TEMPORAL - ver nombre equipo España y parchear partidas nacionales. Borrar tras usar.
$token = $_GET['t'] ?? '';
if (!hash_equals('a8297a53239137937dc1fa44b4f1d937854233439e869dbe4dbc87c9774208b1', hash('sha256', $token))) { http_response_code(403); exit('forbidden'); }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
header('Content-Type: application/json');
$out = [];
try {
    $step = $_GET['step'] ?? 'info';
    if ($step === 'info') {
        // nombre del equipo España
        $teams = DB::table('teams')->where('name', 'like', '%Espa%')->select('id', 'name', 'type')->get();
        $out['spain_teams'] = $teams;
        // partidas nacionales
        $games = DB::table('games as g')->join('teams as t', 't.id', '=', 'g.team_id')
            ->where('t.type', 'national')->select('g.id', 't.name as team')->get();
        $out['national_games'] = $games;
    } elseif ($step === 'patch-national') {
        // Parchea partidas nacionales: añade plantillas nacionales que falten
        $added = DB::insert(<<<SQL
            INSERT INTO game_players (
                id, game_id, player_id,
                transfermarkt_id, sofascore_id, fc26_id, name, date_of_birth, nationality, height, foot,
                team_id, number, position, secondary_positions,
                market_value, market_value_cents, contract_until, annual_wage, release_clause, durability,
                overall_score, potential, potential_low, potential_high, tier
            )
            SELECT
                gen_random_uuid(), g.id, t.player_id,
                t.transfermarkt_id, t.sofascore_id, t.fc26_id, t.name, t.date_of_birth, t.nationality, t.height, t.foot,
                t.team_id,
                COALESCE((SELECT MAX(gp2.number) FROM game_players gp2 WHERE gp2.game_id = g.id AND gp2.team_id = t.team_id), 0)
                  + ROW_NUMBER() OVER (PARTITION BY g.id, t.team_id ORDER BY t.player_id),
                t.position, t.secondary_positions,
                t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.release_clause, t.durability,
                t.overall_score, t.potential, t.potential_low, t.potential_high, t.tier
            FROM games g
            JOIN teams gt ON gt.id = g.team_id AND gt.type = 'national'
            CROSS JOIN game_player_templates t
            JOIN teams tt ON tt.id = t.team_id AND tt.type = 'national'
            WHERE t.season = '2026'
              AND t.team_id = g.team_id
              AND NOT EXISTS (
                  SELECT 1 FROM game_players gp
                  WHERE gp.game_id = g.id AND gp.player_id = t.player_id
              )
            ON CONFLICT (game_id, player_id) DO NOTHING
        SQL);
        $out['patched'] = $added;

        // match_state
        $addedState = DB::insert(<<<'SQL'
            INSERT INTO game_player_match_state (game_player_id, game_id, fitness, morale)
            SELECT gp.id, gp.game_id, t.fitness, t.morale
            FROM game_players gp
            JOIN game_player_templates t
              ON t.player_id = gp.player_id AND t.team_id = gp.team_id AND t.season = '2026'
            JOIN teams tm ON tm.id = gp.team_id AND tm.type = 'national'
            WHERE NOT EXISTS (SELECT 1 FROM game_player_match_state s WHERE s.game_player_id = gp.id)
            ON CONFLICT (game_player_id) DO NOTHING
        SQL);
        $out['patched_state'] = $addedState;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
