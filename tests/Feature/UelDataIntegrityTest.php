<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

/**
 * Static data checks for the pure-knockout Europa Cup rebuild:
 * - data/2026/UELQ/teams.json: 36 clubs (24 entryRound=1, 12 entryRound=2),
 *   no duplicate IDs, and every ID already existed in the pre-rebuild
 *   UEL/UELQ files (the rebuild only moved the 20 direct UEL slots into
 *   UELQ with entry_round=2; no club was invented or dropped).
 * - data/2026/UEL/schedule.json: 4 knockout rounds (R16, QF, SF, F) with
 *   first leg before the return leg.
 */
class UelDataIntegrityTest extends TestCase
{
    private function dataPath(string $relative): string
    {
        return base_path('data/2026/' . $relative);
    }

    private function loadJson(string $relative): array
    {
        $decoded = json_decode(file_get_contents($this->dataPath($relative)), true);
        $this->assertIsArray($decoded, "{$relative} must be valid JSON");

        return $decoded;
    }

    public function test_uelq_teams_have_36_clubs_with_correct_entry_rounds(): void
    {
        $data = $this->loadJson('UELQ/teams.json');

        $clubs = $data['clubs'] ?? null;
        $this->assertIsArray($clubs);
        $this->assertCount(36, $clubs);

        $round1 = array_filter($clubs, fn ($c) => ($c['entryRound'] ?? null) === 1);
        $round2 = array_filter($clubs, fn ($c) => ($c['entryRound'] ?? null) === 2);
        $this->assertCount(24, $round1, '24 clubs must enter in qualifying round 1');
        $this->assertCount(12, $round2, '12 clubs must enter in qualifying round 2');
        $this->assertCount(36, array_merge($round1, $round2), 'every club needs entryRound 1 or 2');

        $ids = array_map(fn ($c) => (string) ($c['id'] ?? ''), $clubs);
        $this->assertNotContains('', $ids, 'every club must have an id');
        $this->assertCount(36, array_unique($ids), 'club ids must not repeat');

        foreach ($clubs as $club) {
            $this->assertNotEmpty($club['name'] ?? null, 'every club must have a name');
            $this->assertNotEmpty($club['country'] ?? null, 'every club must have a country');
        }
    }

    public function test_uelq_teams_all_existed_in_pre_rebuild_uel_and_uelq_files(): void
    {
        $clubs = $this->loadJson('UELQ/teams.json')['clubs'];
        $newIds = array_map(fn ($c) => (string) $c['id'], $clubs);

        // Pre-rebuild state: first parent of the merge that rebuilt UEL/UELQ
        // as pure knockout.
        $repoRoot = escapeshellarg(base_path());
        $oldIds = [];
        foreach (['UEL/teams.json', 'UELQ/teams.json'] as $file) {
            $raw = shell_exec(
                "git -C {$repoRoot} show 392ac49^:data/2026/{$file} 2>/dev/null"
            );
            $this->assertNotEmpty($raw, "cannot read pre-rebuild {$file} from git history");
            $old = json_decode($raw, true);
            $this->assertIsArray($old);
            foreach ($old['clubs'] as $club) {
                $oldIds[] = (string) $club['id'];
            }
        }

        $missing = array_values(array_diff($newIds, $oldIds));
        $this->assertSame(
            [],
            $missing,
            'clubs invented by the rebuild (not in old UEL/UELQ files): ' . implode(', ', $missing)
        );
        $this->assertEqualsCanonicalizing(
            $oldIds,
            $newIds,
            'the rebuild must neither drop nor invent clubs: the new UELQ field is exactly old UEL + old UELQ'
        );
    }

    public function test_uel_final_phase_has_empty_club_list(): void
    {
        $data = $this->loadJson('UEL/teams.json');

        $this->assertSame('UEL', $data['id'] ?? null);
        $this->assertSame(
            [],
            $data['clubs'] ?? null,
            'UEL final-phase clubs must be empty: the 16 entrants arrive from UELQ round 2 at runtime'
        );
    }

    public function test_uel_schedule_has_4_rounds_with_first_leg_before_return(): void
    {
        $rounds = $this->loadJson('UEL/schedule.json')['knockout'] ?? null;
        $this->assertIsArray($rounds);
        $this->assertCount(4, $rounds, 'UEL needs 4 knockout rounds (R16, QF, SF, F)');

        $numbers = [];
        foreach ($rounds as $round) {
            $numbers[] = $round['round'];
            $this->assertNotEmpty($round['name'] ?? null, 'every round needs a name');

            $first = Carbon::parse($round['first_leg_date']);
            $second = Carbon::parse($round['second_leg_date']);
            $this->assertTrue(
                $first->lt($second),
                "round {$round['round']}: first leg ({$round['first_leg_date']}) must be before the return leg ({$round['second_leg_date']})"
            );
        }
        $this->assertSame([1, 2, 3, 4], $numbers);
    }

    public function test_uelq_schedule_has_2_rounds_with_first_leg_before_return(): void
    {
        $rounds = $this->loadJson('UELQ/schedule.json')['knockout'] ?? null;
        $this->assertIsArray($rounds);
        $this->assertCount(2, $rounds, 'UELQ needs 2 qualifying rounds');

        foreach ($rounds as $round) {
            $this->assertTrue(
                Carbon::parse($round['first_leg_date'])->lt(Carbon::parse($round['second_leg_date'])),
                "UELQ round {$round['round']}: first leg must be before the return leg"
            );
        }
    }
}
