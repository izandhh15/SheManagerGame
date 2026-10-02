<?php

namespace App\Models;

use App\Modules\Competition\Configs\DefaultLeagueConfig;
use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Services\CountryConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $name
 * @property string $country
 * @property string|null $flag
 * @property int $tier
 * @property string $type
 * @property string $season
 * @property string $handler_type
 * @property string $role
 * @property string $scope
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @method static \Database\Factories\CompetitionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereHandlerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Competition whereType($value)
 * @mixin \Eloquent
 */
class Competition extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public const ROLE_LEAGUE = 'league';
    public const ROLE_DOMESTIC_CUP = 'domestic_cup';
    public const ROLE_EUROPEAN = 'european';
    public const ROLE_TEAM_POOL = 'team_pool';

    /** @deprecated Use ROLE_LEAGUE instead — kept only for migration compatibility */
    public const ROLE_PRIMARY = 'league';
    /** @deprecated Use ROLE_LEAGUE instead — kept only for migration compatibility */
    public const ROLE_FOREIGN = 'league';

    public const SCOPE_DOMESTIC = 'domestic';
    public const SCOPE_CONTINENTAL = 'continental';

    /**
     * Short display names for competitions, keyed by competition ID.
     * Domestic cups declare theirs in config/countries.php instead
     * (`domestic_cups.<cup>.short_name`); this map covers leagues, UEFA
     * competitions and the odd fixed competition. Falls back to the full
     * name if not mapped anywhere.
     */
    private const SHORT_NAMES = [
        'ESP1'    => 'Liga F',
        'ESP2'    => 'Primera Federación',
        'ESP3A'   => 'Segunda Federación I',
        'ESP3B'   => 'Segunda Federación II',
        'ESP3C'   => 'Segunda Federación III',
        'ESPCUP'  => 'Copa de la Reina',
        'ESPSUP'  => 'Supercopa',
        'ENG1'    => 'WSL',
        'DEU1'    => 'Frauen-Bundesliga',
        'FRA1'    => 'Première Ligue',
        'ITA1'    => 'Serie A Fem.',
        'POR1'    => 'Liga BPI',
        'NED1'    => 'Eredivisie Vr.',
        'UCL'     => 'UWCL',
        'UEL'     => 'Europa Cup Fem.',
        'UCLQ'    => 'Previa UWCL',
        'UELQ'    => 'Previa Europa Cup',
        'WC2026'  => 'Mundial',
        'WQUEFA'  => 'Clasificación · UEFA',
        'WQAFC'   => 'Clasificación · AFC',
        'WQCAF'   => 'Clasificación · CAF',
        'WQCONC'  => 'Clasificación · CONCACAF',
        'WQCONM'  => 'Clasificación · CONMEBOL',
        'WQOFC'   => 'Clasificación · OFC',
        'WNL'     => 'Nations League',
        'WWCU27'  => 'Mundial 2027',
        'WOLYMP'  => 'Juegos Olímpicos',
        'WEURO'   => 'Eurocopa',
        'WEUROQ'  => 'Clasificación · Euro',
        'CWC'     => 'Mundial de Clubes',
        'PRESEASON' => 'Amistoso',
        'FRIENDLY' => 'Amistoso',
    ];

    // Ultra-compact tags for tight layouts (narrow dashboard column). Domestic
    // cups declare theirs in config (`domestic_cups.<cup>.abbreviation`).
    // Falls back to shortName() for anything not listed anywhere.
    private const ABBREVIATIONS = [
        'ESP1'    => 'Liga F',
        'ESP2'    => '1ª Fed',
        'ESP3A'   => '2ª Fed I',
        'ESP3B'   => '2ª Fed II',
        'ESP3C'   => '2ª Fed III',
        'ESPCUP'  => 'Copa',
        'ESPSUP'  => 'Supercopa',
        'ENG1'    => 'WSL',
        'DEU1'    => 'BL Fem.',
        'FRA1'    => 'Première',
        'ITA1'    => 'Serie A F',
        'POR1'    => 'Liga BPI',
        'NED1'    => 'Eredivisie V',
        'UCL'     => 'UWCL',
        'UEL'     => 'UEC',
        'UCLQ'    => 'PR-UWCL',
        'UELQ'    => 'PR-UEC',
        'CWC'     => 'Mundial',
    ];

    // Spanish grammatical article per competition. Women's competitions are all
    // feminine ("la Liga F", "la Copa de la Reina", "la UWCL", "la Supercopa").
    // Falls back to "la" for anything not listed. "los Juegos Olímpicos"
    // is the only masculine-plural name in the game.
    private const ARTICLES = [
        'WC2026'    => 'el',
        'PRESEASON' => 'el',
        'FRASUP'    => 'el',
        'DEUSUP'    => 'el',
        'WOLYMP'    => 'los',
        'CWC'       => 'el',
    ];

    protected $fillable = [
        'id',
        'name',
        'country',
        'flag',
        'tier',
        'type',
        'role',
        'scope',
        'season',
        'handler_type',
    ];

    protected $casts = [
        'tier' => 'integer',
    ];

    /** @see CompetitionTeam::SEASON_MATCHES_COMPETITION — teams for this competition's own season. */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'competition_teams')
            ->withPivot('season')
            ->orderBy('name')
            ->whereRaw(CompetitionTeam::SEASON_MATCHES_COMPETITION);
    }

    public function shortName(): string
    {
        return app(CountryConfig::class)->cupShortName($this->id)
            ?? self::SHORT_NAMES[$this->id]
            ?? $this->name;
    }

    public function abbreviation(): string
    {
        return app(CountryConfig::class)->cupAbbreviation($this->id)
            ?? self::ABBREVIATIONS[$this->id]
            ?? $this->shortName();
    }

    /**
     * Spanish grammatical article for this competition's short name:
     * "la" (la Liga, la Champions League), "el" (el Mundial), or null.
     */
    public function getArticleAttribute(): ?string
    {
        return self::ARTICLES[$this->id] ?? 'la';
    }

    /**
     * Short name with "de" preposition: "de la Champions League", "del Mundial".
     */
    public function nameWithDe(): string
    {
        return match ($this->article) {
            'la' => 'de la ' . $this->shortName(),
            'los' => 'de los ' . $this->shortName(),
            null => 'de ' . $this->shortName(),
            default => 'del ' . $this->shortName(),
        };
    }

    /**
     * Short name with "a" preposition: "a la Champions League", "al Mundial".
     */
    public function nameWithA(): string
    {
        return match ($this->article) {
            'la' => 'a la ' . $this->shortName(),
            'los' => 'a los ' . $this->shortName(),
            null => 'a ' . $this->shortName(),
            default => 'al ' . $this->shortName(),
        };
    }

    /**
     * Short name with "en" preposition: "en la Champions League", "en el Mundial".
     */
    public function nameWithEn(): string
    {
        return match ($this->article) {
            'la' => 'en la ' . $this->shortName(),
            'los' => 'en los ' . $this->shortName(),
            null => 'en ' . $this->shortName(),
            default => 'en el ' . $this->shortName(),
        };
    }

    /**
     * Short name with definite article as nominative: "la Champions League",
     * "el Mundial". Article is lowercase; use the capitalized placeholder
     * (:Competition_el) or Str::ucfirst() at the call site to start a sentence.
     */
    public function nameWithEl(): string
    {
        return match ($this->article) {
            'la' => 'la ' . $this->shortName(),
            'los' => 'los ' . $this->shortName(),
            null => $this->shortName(),
            default => 'el ' . $this->shortName(),
        };
    }

    public function isLeague(): bool
    {
        return in_array($this->handler_type, ['league', 'league_with_playoff', 'swiss_format', 'group_stage_cup']);
    }

    public function isCup(): bool
    {
        return !$this->isLeague();
    }

    /**
     * Whether this is a domestic round-robin league (ESP1, ENG1, …) as opposed
     * to a cup or a continental competition. Unlike isLeague(), this EXCLUDES
     * the continental Swiss/group formats. Used to decide whether a match's
     * strength normalization should use that league's own rating band (domestic
     * league) or the global cross-band scale (cups, Europe, World Cup).
     */
    public function isDomesticLeague(): bool
    {
        return in_array($this->handler_type, ['league', 'league_with_playoff'], true)
            && $this->role === self::ROLE_LEAGUE
            && $this->scope === self::SCOPE_DOMESTIC;
    }

    /**
     * Whether this is a domestic tier league (tier >= 1).
     * Replaces the old ROLE_PRIMARY / ROLE_FOREIGN distinction.
     */
    public function isTierLeague(): bool
    {
        return $this->role === self::ROLE_LEAGUE && $this->tier >= 1;
    }

    /**
     * Get the configuration for this competition.
     * Checks country config for a specific config class, falls back to defaults.
     */
    public function getConfig(): CompetitionConfig
    {
        // Check country config for a specific config class
        $configClass = app(CountryConfig::class)->configClassForCompetition($this->id);
        if ($configClass) {
            return new $configClass();
        }

        // Default config based on number of teams in the competition
        $numTeams = $this->teams()->count();
        if ($numTeams === 0) {
            $numTeams = 20; // Fallback
        }

        // Scale base TV revenue by tier
        $baseTvRevenue = match ($this->tier) {
            1 => 5_000_000_000,  // €50M base for tier 1
            2 => 1_000_000_000,  // €10M base for tier 2
            default => 500_000_000, // €5M base for lower tiers
        };

        return new DefaultLeagueConfig($numTeams, $baseTvRevenue);
    }
}
