<?php
// TEMPORAL - re-sincroniza plantillas de clubs desde JSON + parchea partidas.
// Borrar tras usar.
$token = $_GET['t'] ?? '';
if (!hash_equals('a8297a53239137937dc1fa44b4f1d937854233439e869dbe4dbc87c9774208b1', hash('sha256', $token))) {
    http_response_code(403);
    exit('forbidden');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

header('Content-Type: application/json');
$out = [];
$step = $_GET['step'] ?? 'refresh';

if ($step === 'refresh') {
    // 1. Regenera plantillas de CLUBS desde el JSON actual (ratings FC27 incluidos).
    //    No toca las plantillas de selecciones.
    $before = DB::table('game_player_templates')->where('season', '2026')->count();
    $exit = Artisan::call('app:refresh-player-templates', ['--season' => '2026']);
    $out['artisan_exit'] = $exit;
    $out['artisan_output'] = Artisan::output();
    $out['templates_before'] = $before;
    $out['templates_after'] = DB::table('game_player_templates')->where('season', '2026')->count();
    // Verificacion: los clubs que estaban cortos
    $out['check'] = DB::table('game_player_templates as p')
        ->join('teams as t', 't.id', '=', 'p.team_id')
        ->where('p.season', '2026')
        ->whereIn('t.name', ['Grêmio', 'Cruzeiro', 'Internacional', 'Sevilla FC', 'SD Eibar', 'Real Sociedad', 'FC Luzern'])
        ->select('t.name', DB::raw('count(*) as n'), DB::raw('max(p.overall_score) as max_ovr'))
        ->groupBy('t.name')->orderBy('t.name')->get();
}

if ($step === 'patch-games') {
    // 2. Para cada partida de CLUB: inserta las jugadoras que falten (las nuevas del refresh).
    //    No toca partidas de seleccion.
    $games = DB::table('games as g')
        ->join('teams as t', 't.id', '=', 'g.team_id')
        ->where('t.type', '!=', 'national')
        ->select('g.id', 't.name as team')->get();
    $out['games_patched'] = [];
    foreach ($games as $g) {
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
                gen_random_uuid(), ?, t.player_id,
                t.transfermarkt_id, t.sofascore_id, t.fc26_id, t.name, t.date_of_birth, t.nationality, t.height, t.foot,
                t.team_id, t.number, t.position, t.secondary_positions,
                t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.release_clause, t.durability,
                t.overall_score,
                t.potential, t.potential_low, t.potential_high, t.tier
            FROM game_player_templates t
            WHERE t.season = '2026'
              AND t.team_id NOT IN (SELECT id FROM teams WHERE type = 'national')
            ON CONFLICT (game_id, player_id) DO NOTHING
        SQL, [$g->id]);

        $addedState = DB::insert(<<<'SQL'
            INSERT INTO game_player_match_state (game_player_id, game_id, fitness, morale)
            SELECT gp.id, gp.game_id, t.fitness, t.morale
            FROM game_players gp
            JOIN game_player_templates t
              ON t.player_id = gp.player_id
             AND t.team_id = gp.team_id
             AND t.season = '2026'
            WHERE gp.game_id = ?
            ON CONFLICT (game_player_id) DO NOTHING
        SQL, [$g->id]);

        // cuantas tiene ahora el equipo del usuario en esa partida
        $userTeamCount = DB::table('game_players')->where('game_id', $g->id)
            ->whereIn('team_id', function ($q) use ($g) {
                $q->select('team_id')->from('games')->where('id', $g->id);
            })->count();

        $out['games_patched'][] = [
            'team' => $g->team, 'id' => substr($g->id, 0, 8),
            'user_squad_now' => $userTeamCount,
        ];
    }
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
