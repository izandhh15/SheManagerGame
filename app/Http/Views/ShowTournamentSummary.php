<?php

namespace App\Http\Views;

use App\Models\TournamentSummary;

class ShowTournamentSummary
{
    public function __invoke(string $summaryId)
    {
        $summary = TournamentSummary::with(['team', 'competition'])->findOrFail($summaryId);

        abort_if((int) $summary->user_id !== (int) auth()->id(), 403);

        // summary_data is a free-form JSON blob that persists across deploys:
        // an old-schema snapshot must degrade gracefully instead of 500ing.
        $data = $summary->summary_data ?? [];

        // your_record is another persisted blob the blade reads directly
        // ($yourRecord['goalsFor'] etc.) — normalize missing keys to 0.
        $yourRecord = array_merge(
            ['played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'goalsFor' => 0, 'goalsAgainst' => 0],
            $summary->your_record ?? []
        );

        // Convert teams map arrays to stdClass for <x-team-crest> compatibility
        $teams = [];
        foreach ($data['teams'] ?? [] as $id => $teamData) {
            $teams[$id] = (object) $teamData;
        }

        return view('tournament-summary', [
            'summary' => $summary,
            'competition' => $summary->competition,
            'teams' => $teams,
            'playerTeamId' => $data['player_team_id'] ?? null,
            'competitionName' => $data['competition_name'] ?? null,
            'championTeamId' => $data['champion_team_id'] ?? null,
            'finalistTeamId' => $data['finalist_team_id'] ?? null,
            'groupStandings' => $data['group_standings'] ?? [],
            'knockoutTies' => $data['knockout_ties'] ?? [],
            'yourMatches' => $data['your_matches'] ?? [],
            'finalMatch' => $data['final_match'] ?? null,
            'finalGoalEvents' => $data['final_goal_events'] ?? [],
            'topScorers' => $data['top_scorers'] ?? [],
            'topAssisters' => $data['top_assisters'] ?? [],
            'topGoalkeepers' => $data['top_goalkeepers'] ?? [],
            'topMvps' => $data['top_mvps'] ?? [],
            'yourSquadStats' => $data['your_squad_stats'] ?? [],
            'mvpCounts' => $data['mvp_counts'] ?? [],
            'resultLabel' => $summary->result_label,
            'yourRecord' => $yourRecord,
            'playerStanding' => $data['player_standing'] ?? null,
        ]);
    }
}
