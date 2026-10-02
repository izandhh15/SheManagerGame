<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the 0.3.9 duplicate sweep: every national-team player that also
 * exists in a club squad must reuse the club's id, so the deterministic
 * player_id (uuid5 of the id) dedups templates and the squad picker never
 * shows the same real-world player twice.
 */
class NoDuplicatePlayersTest extends TestCase
{
    private function norm(string $s): string
    {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = strtolower($s);
        $s = preg_replace('/[^a-z ]/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    public function test_no_club_vs_national_duplicates(): void
    {
        $base = base_path('data/2026');

        // Club players: normalized name => set of ids
        $clubIds = [];
        foreach (glob($base . '/*/*.json') as $file) {
            if (str_ends_with($file, 'NAT.json')) {
                continue;
            }
            $data = json_decode(file_get_contents($file), true);
            foreach ($data['players'] ?? [] as $p) {
                if (empty($p['id']) || empty($p['name'])) {
                    continue;
                }
                $clubIds[$this->norm($p['name'])][(string) $p['id']] = true;
            }
        }

        // National players whose id is synthetic (91xxxx) but whose name
        // matches a club player with a different id = duplicate.
        $nat = json_decode(file_get_contents($base . '/NAT.json'), true);
        $dupes = [];
        foreach ($nat['clubs'] as $team) {
            foreach ($team['players'] as $p) {
                $nid = (string) ($p['id'] ?? '');
                $name = $p['name'] ?? '';
                if (!preg_match('/^91[0-6]\d{3}$/', $nid) || $name === '') {
                    continue;
                }
                $key = $this->norm($name);
                if (isset($clubIds[$key]) && !isset($clubIds[$key][$nid])) {
                    $dupes[] = "{$team['name']}: {$name} (nat {$nid} vs club " . implode(',', array_keys($clubIds[$key])) . ')';
                }
            }
        }

        $this->assertEmpty($dupes, 'Duplicated players (national id != club id): ' . implode(' | ', array_slice($dupes, 0, 10)));
    }

    public function test_no_duplicate_names_within_club_files(): void
    {
        $base = base_path('data/2026');
        $seen = [];
        $dupes = [];
        foreach (glob($base . '/*/*.json') as $file) {
            if (str_ends_with($file, 'NAT.json')) {
                continue;
            }
            $data = json_decode(file_get_contents($file), true);
            foreach ($data['players'] ?? [] as $p) {
                if (empty($p['id']) || empty($p['name'])) {
                    continue;
                }
                $key = $this->norm($p['name']);
                $id = (string) $p['id'];
                if (isset($seen[$key]) && $seen[$key] !== $id) {
                    $dupes[] = "{$p['name']} ({$seen[$key]} vs {$id})";
                }
                $seen[$key] = $id;
            }
        }

        $this->assertEmpty($dupes, 'Duplicate names within club files: ' . implode(' | ', array_slice($dupes, 0, 10)));
    }
}
