<?php

namespace App\Modules\Manager\Services;

use App\Models\ManagerTrophy;
use Illuminate\Database\Eloquent\Collection;

/**
 * Groups a manager's trophies into ordered sections for the palmarés
 * ("Trophy room") screen.
 *
 * Section mapping:
 *   league          -> Ligas
 *   cup             -> Copas
 *   supercup        -> Supercopas
 *   european        -> Internacionales (continental/world competitions)
 *   friendly_trophy -> Torneos de pretemporada (shows the custom trophy name)
 *   anything else   -> Otros
 *
 * Within each section trophies keep the order they were given in
 * (ManagerProfileService::getTrophies returns them most-recent first).
 */
class TrophySectionService
{
    /**
     * Sections in display order. 'other' intentionally has an empty type
     * list: it is the catch-all for any trophy_type not listed above.
     *
     * @var array<string, array{types: list<string>}>
     */
    public const SECTIONS = [
        'leagues' => ['types' => ['league']],
        'cups' => ['types' => ['cup']],
        'supercups' => ['types' => ['supercup']],
        'international' => ['types' => ['european']],
        'preseason' => ['types' => ['friendly_trophy']],
        'other' => ['types' => []],
    ];

    /**
     * Returns the section key a trophy type belongs to.
     */
    public function sectionForType(string $trophyType): string
    {
        foreach (self::SECTIONS as $key => $section) {
            if (in_array($trophyType, $section['types'], true)) {
                return $key;
            }
        }

        return 'other';
    }

    /**
     * @return list<array{key:string,title:string,subtitle:string,count:int,trophies:list<array{name:string,team:string,season:string,trophy_type:string}>}>
     */
    public function groupTrophies(Collection $trophies): array
    {
        $buckets = [];

        foreach ($trophies as $trophy) {
            $section = $this->sectionForType($trophy->trophy_type);
            $buckets[$section][] = [
                'name' => $this->displayName($trophy),
                'team' => $trophy->team?->name ?? '',
                'season' => (string) $trophy->season,
                'trophy_type' => $trophy->trophy_type,
            ];
        }

        $result = [];
        foreach (self::SECTIONS as $key => $section) {
            if (empty($buckets[$key])) {
                continue;
            }

            $result[] = [
                'key' => $key,
                'title' => __("profile.trophy_section_{$key}_title"),
                'subtitle' => __("profile.trophy_section_{$key}_subtitle"),
                'count' => count($buckets[$key]),
                'trophies' => $buckets[$key],
            ];
        }

        return $result;
    }

    /**
     * Display name for a trophy row. Preseason (friendly) trophies show
     * their custom trophy name (e.g. "Trofeu TM"); everything else shows
     * the competition name.
     */
    public function displayName(ManagerTrophy $trophy): string
    {
        if ($trophy->trophy_type === 'friendly_trophy') {
            return (string) ($trophy->custom_name ?: __('profile.friendly_trophy_unnamed'));
        }

        $name = $trophy->competition?->name
            ?? $trophy->custom_name
            ?? $trophy->competition_id;

        return (string) ($name ? __($name) : __('profile.unknown_trophy'));
    }
}
