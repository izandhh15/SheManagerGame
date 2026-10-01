<?php

namespace App\Http\Views;

use App\Models\Team;
use App\Modules\Season\Services\GamePlayerTemplateService;
use App\Modules\Season\Services\NationalSquadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only preview of a national team's call-up BEFORE creating a game.
 *
 * Reads the squad straight from data/2026/NAT.json (the 23-player list) and
 * enriches each player with their live template data (overall, club, age),
 * so the user can browse e.g. "all Spain players from Barça" before picking
 * a club in career mode. No game_id needed.
 */
final class ShowNationalSquadPreview
{
    public function __invoke(Request $request, string $teamId)
    {
        $team = Team::where('type', 'national')
            ->where('is_placeholder', false)
            ->find($teamId);

        if (!$team || $team->type !== 'national') {
            abort(404);
        }

        $season = NationalSquadService::TEMPLATE_SEASON;

        // The 23-player squad lives in NAT.json. Match the file entry to the
        // DB team by FIFA code (youth teams have no code → match by name).
        $natPlayers = $this->natSquadFor($team);

        $playerIds = [];
        foreach ($natPlayers as $p) {
            if (!empty($p['id'])) {
                $playerIds[] = GamePlayerTemplateService::playerIdFor((string) $p['id']);
            }
        }

        $templates = DB::table('game_player_templates')
            ->where('season', $season)
            ->where('team_id', $team->id)
            ->whereIn('player_id', $playerIds)
            ->get()
            ->keyBy('player_id');

        // Club for each player (from their club template, if any) — one query.
        $clubByPlayerId = DB::table('game_player_templates as t')
            ->join('teams', 'teams.id', '=', 't.team_id')
            ->where('t.season', $season)
            ->whereIn('t.player_id', $playerIds)
            ->where('teams.type', '!=', 'national')
            ->orderBy('t.player_id')
            ->pluck('teams.name', 't.player_id');

        $groupOrder = array_flip(ShowNationalSquadPicker::POSITION_GROUPS);

        $players = collect($natPlayers)->map(function ($p) use ($templates, $clubByPlayerId, $playerIds) {
            $playerId = !empty($p['id']) ? GamePlayerTemplateService::playerIdFor((string) $p['id']) : null;
            $tpl = $playerId ? ($templates[$playerId] ?? null) : null;

            $name = $tpl->name ?? $p['name'];
            $position = $tpl->position ?? $p['position'] ?? '';
            $overall = $tpl ? (int) $tpl->overall_score : (int) ($p['overall_score'] ?? 0);

            $dob = $tpl && $tpl->date_of_birth ? \Carbon\Carbon::parse($tpl->date_of_birth) : null;
            $isPlaceholderDob = $dob && $dob->format('Y-m-d') === '2000-01-01';

            return [
                'name' => $name,
                'position' => $position,
                'group' => ShowNationalSquadPicker::positionGroup($position),
                'overall' => $overall,
                'age' => ($dob && !$isPlaceholderDob) ? (int) $dob->diffInYears(now()) : null,
                'club' => $playerId ? ($clubByPlayerId[$playerId] ?? null) : null,
            ];
        })->sortBy([
            fn ($p) => $groupOrder[$p['group']] ?? 99,
            fn ($p) => -$p['overall'],
            fn ($p) => $p['name'],
        ])->values();

        return view('national-squad-preview', [
            'team' => $team,
            'players' => $players,
            'clubs' => $players->pluck('club')->filter()->unique()->sort()->values(),
        ]);
    }

    /**
     * @return array<int, array{id?: string, name: string, position?: string, overall_score?: int}>
     */
    private function natSquadFor(Team $team): array
    {
        $path = base_path('data/' . NationalSquadService::TEMPLATE_SEASON . '/NAT.json');
        if (!file_exists($path)) {
            return [];
        }

        $clubs = json_decode(file_get_contents($path), true)['clubs'] ?? [];

        foreach ($clubs as $club) {
            if ($team->fifa_code && !empty($club['fifa_code']) && $club['fifa_code'] === $team->fifa_code) {
                return array_slice($club['players'] ?? [], 0, 23);
            }
        }

        // Fallback for teams without FIFA code (youth sides): match by name.
        foreach ($clubs as $club) {
            if (($club['name'] ?? null) === $team->name) {
                return array_slice($club['players'] ?? [], 0, 23);
            }
        }

        return [];
    }
}
