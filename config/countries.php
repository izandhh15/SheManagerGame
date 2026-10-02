<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Football Country Configurations
    |--------------------------------------------------------------------------
    |
    | Each country declares its full football ecosystem: playable league tiers,
    | domestic cups, promotion/relegation rules, continental qualification slots,
    | and support teams needed for transfers and continental competitions.
    |
    | This config is the single source of truth for country-specific setup.
    | Processors, seeders, and game creation all read from here.
    |
    */

    'ES' => [
        'name' => 'España',

        'tiers' => [
            1 => [
                'competition' => 'ESP1',
                'teams' => 16,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\LaLigaConfig::class,
            ],
            2 => [
                'competition' => 'ESP2',
                'teams' => 14,
                'handler' => 'league_with_playoff',
                'config_class' => \App\Modules\Competition\Configs\LaLiga2Config::class,
            ],
            3 => [
                'competition' => 'ESP3A',
                'teams' => 14,
                'handler' => 'league_with_playoff',
                'config_class' => \App\Modules\Competition\Configs\PrimeraRFEFConfig::class,
                // Segunda Federación has three parallel groups of 14 teams.
                // ESP3A is the "primary" entry so existing call sites that
                // expect one competition per tier continue to work; ESP3B and
                // ESP3C are enumerated via
                // CountryConfig::tierCompetitionIds()/siblings.
                'siblings' => [
                    [
                        'competition' => 'ESP3B',
                        'teams' => 14,
                        'handler' => 'league_with_playoff',
                        'config_class' => \App\Modules\Competition\Configs\PrimeraRFEFConfig::class,
                    ],
                    [
                        'competition' => 'ESP3C',
                        'teams' => 14,
                        'handler' => 'league_with_playoff',
                        'config_class' => \App\Modules\Competition\Configs\PrimeraRFEFConfig::class,
                    ],
                ],
            ],
        ],

        // Domestic cups. Each entry is the complete description of one cup —
        // everything the engine needs beyond the participant list and round
        // calendar in data/<season>/<cup>/. See docs/game-systems/domestic-cups.md
        // for the full key reference; the short version:
        //
        // - handler / config_class / draw_pairing: how the cup runs and pays.
        // - short_name / abbreviation: compact labels for tight layouts.
        // - neutral_venues: round name => venue for ties played away from
        //   the home ground. '*' applies to every round (final-four style).
        'domestic_cups' => [
            'ESPCUP' => [
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\KnockoutCupConfig::class,
                'draw_pairing' => \App\Modules\Competition\Services\Draw\CrossCategoryPairing::class,
                'short_name' => 'Copa de la Reina',
                'abbreviation' => 'Copa',
                'neutral_venues' => [
                    'cup.final' => ['name' => 'La Cartuja', 'capacity' => 70000],
                ],
            ],
            'ESPSUP' => [
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\SupercupConfig::class,
                // Not drawn: the semi-finals follow from the qualifying
                // seeds (cup winner v league runner-up, cup runner-up v
                // league champion).
                'draw_pairing' => \App\Modules\Competition\Services\Draw\SeededBracketPairing::class,
                'short_name' => 'Supercopa',
                'abbreviation' => 'Supercopa',
                // Final four hosted in Spain: semis and final alike. The
                // women's Supercopa rotates its neutral Spanish venue —
                // Estadio Castalia (Castellón de la Plana) hosted the 2026
                // edition after two years at Butarque (Leganés).
                'neutral_venues' => [
                    '*' => ['name' => 'Estadio Castalia', 'capacity' => 15500],
                ],
            ],
        ],

        // Supercup derivation. 'teams' picks the format: 4 = final four
        // (both cup finalists + league top two, RFEF cascade rules), 2 =
        // champion v cup winner (league runner-up steps in on a double).
        // The cup final is located from the cup's own schedule.json, so no
        // round number needs repeating here. 'cup_entry_round' is the round
        // the supercup field skips ahead to in the main cup: Spain's four
        // supercup clubs join the Copa de la Reina at the round of 16
        // (round 4); omit it when the supercup has no bearing on cup entry.
        'supercup' => [
            'competition' => 'ESPSUP',
            'cup' => 'ESPCUP',
            'league' => 'ESP1',
            'teams' => 4,
            'cup_entry_round' => 4,
        ],

        // Rules for which teams from playable tiers qualify for each domestic
        // cup at the start of the following season. Reserve teams never
        // qualify regardless of their finishing position.
        //
        // - auto_qualify_tiers: every team currently in these tiers qualifies.
        // - top_per_group: top N teams in each competition at this tier
        //   (including siblings — ESP3A, ESP3B and ESP3C) qualify.
        //
        // Copa de la Reina (48 clubs): 16 from Liga F + 8 from Primera
        // Federación + 8 per Segunda Federación group (3 groups) = 48.
        'cup_qualification' => [
            'ESPCUP' => [
                'auto_qualify_tiers' => [1],
                'top_per_group' => [
                    2 => 8,
                    3 => 8,
                ],
                // Total cup size invariant. After auto_qualify +
                // top_per_group + the reserve cascade, the processor tops up
                // round-robin from the top_per_group groups until the field
                // reaches this number.
                'target_size' => 48,
            ],
        ],

        'promotions' => [
            [
                'top_division' => 'ESP1',
                'bottom_division' => 'ESP2',
                'relegated_positions' => [15, 16],
                // Slot counts (not positions) — PromotionSlotAllocator walks
                // standings in order, skipping reserve teams whose parent club
                // is in the top division, and assigns the first $direct_count
                // eligible teams to direct promotion before handing the next
                // $playoff_count to the bracket. This guarantees the two
                // lists are disjoint even when reserves cluster at the top
                // and shift the actual filling past the notional positions.
                'direct_count' => 1,
                'playoff_count' => 4,
                'playoff_generator' => \App\Modules\Competition\Playoffs\ESP2PlayoffGenerator::class,
            ],
            [
                // ESP2 ↔ Segunda Federación (ESP3A + ESP3B + ESP3C).
                //
                // Split-format branch: the champion of each of the three
                // groups goes up directly (direct_count = 1 per group), and
                // a fourth team comes up through the ESP3PO playoff bracket
                // (3 runners-up + best third, single final → 1 winner), so
                // four must go down from ESP2 to keep it at 14 teams (the
                // planner enforces exact tier sizes every season).
                'top_division' => 'ESP2',
                'bottom_division' => 'ESP3A',
                'relegated_positions' => [11, 12, 13, 14],
                'direct_count' => 1,
                'playoff_count' => 1,
                'playoff_winners_count' => 1,
                'playoff_competition' => 'ESP3PO',
                'playoff_generator' => \App\Modules\Competition\Playoffs\SegundaFederacionPlayoffGenerator::class,
                'playoff_source_divisions' => ['ESP3A', 'ESP3B', 'ESP3C'],
            ],
        ],

        // Promotion playoff bracket for Segunda Federación → Primera
        // Federación. Bare knockout competition seeded by
        // SeedReferenceData; the bracket itself is generated by
        // SegundaFederacionPlayoffGenerator when the ESP3 regular season
        // ends (3 runners-up + best third, single final → 1 winner).
        'promotion_playoffs' => [
            'ESP3PO' => [
                'parent_tier' => 2,
                'handler' => 'knockout_cup',
                'name' => 'Playoff Ascenso Segunda Federación',
            ],
        ],

        // Reserve teams that cannot be promoted to the same division as their
        // parent. Maps child transfermarkt_id => parent transfermarkt_id,
        // sourced from the women's data set (data-raw/*.json). Granada CF B
        // has no entry: its parent club is absent from the collected data,
        // and linkReserveTeams skips pairs whose parent is not seeded.
        'reserve_teams' => [
            5474  => 44,    // Athletic Bilbao II → Athletic Bilbao
            7440  => 1129,  // Atlético Madrid B → Club Atlético de Madrid
            8237  => 7499,  // CA Osasuna B → CA Osasuna
            16819 => 16818, // CD Tenerife Femenino B → CD Tenerife Femenino
            7192  => 552,   // Espanyol Barcelona B → Espanyol Barcelona
            12513 => 1132,  // FC Barcelona C → F.C. Barcelona
            4644  => 1132,  // FC Barcelona II → F.C. Barcelona
            7189  => 1143,  // FC Valencia B → Valencia Féminas Club de Fútbol
            7195  => 5649,  // Madrid CFF B → Madrid CFF
            7441  => 7278,  // RC Deportivo A Coruña B → RC Deportivo A Coruña
            8292  => 6551,  // Real Madrid B → Real Madrid
            7889  => 1135,  // Real Sociedad San Sebastián B → Real Sociedad
            8152  => 1133,  // SD Eibar B → SD Éibar
            7193  => 210,   // UD Levante B → Levante UD
            17107 => 17008, // FC Ona Sant Adria → FC Badalona Women
            8421  => 4897,  // Granada CF B → Granada CF
        ],

        // UWCL slots for Liga F's top three; the fourth qualifies for the
        // UEFA Women's Europa Cup (internal code UEL).
        'continental_slots' => [
            'ESP1' => [
                'UCL' => [1],
                'UCLQ' => [2, 3],
                // Spain sends nobody directly to the Europa Cup — Spanish
                // teams only reach it by dropping from the UWCL playoff.
            ],
        ],

        // The Copa de la Reina grants no European place in women's football.
        'cup_winner_slot' => [],

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UCLQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
            'UELQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Support teams: non-playable teams needed for competition and transfers
        |----------------------------------------------------------------------
        |
        | Categories (initialized in this order during game setup):
        |   1. transfer_pool — foreign league teams for scouting/transfers/loans
        |   2. continental   — opponents in UEFA competitions (reuse pool rosters)
        |
        | Domestic cup teams (ESPCUP lower-division) are linked at seeding time
        | but don't need GamePlayer rosters — early rounds are auto-simulated.
        */
        'support' => [
            'transfer_pool' => [
                // Other top-flight leagues — full rosters from JSON, eagerly loaded at game setup
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'POR1' => ['role' => 'league', 'handler' => 'league', 'country' => 'PT', 'from_season' => '2026'],
                'NED1' => ['role' => 'league', 'handler' => 'league', 'country' => 'NL', 'from_season' => '2026'],
                // EUR club pool — individual team files, for European clubs
                // outside the modelled leagues
                'SUI1' => ['role' => 'league', 'handler' => 'league', 'country' => 'CH', 'from_season' => '2026'],
                'FRA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR', 'from_season' => '2026'],
                'ITA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT', 'from_season' => '2026'],
                'DEU2' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE', 'from_season' => '2026'],
                'ENG2' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN', 'from_season' => '2026'],
                'ARG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'AR', 'from_season' => '2026'],
                'BRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'BR', 'from_season' => '2026'],
                'MEX1' => ['role' => 'league', 'handler' => 'league', 'country' => 'MX', 'from_season' => '2026'],
                'USA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'US', 'from_season' => '2026'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
                // INT club pool — non-European clubs (South America, MLS, etc.)
                // for transfer market only; never participates in fixtures
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                // Teams needed for European competitions — rosters reused from
                // tiers + transfer_pool where possible, gaps filled from EUR pool
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UCLQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
                'UELQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'EN' => [
        'name' => 'England',

        'tiers' => [
            1 => [
                'competition' => 'ENG1',
                'teams' => 14,
                'handler' => 'league_with_playoff',
                'config_class' => \App\Modules\Competition\Configs\PremierLeagueConfig::class,
            ],
            2 => [
                'competition' => 'ENG2',
                'teams' => 12,
                'handler' => 'league_with_playoff',
                'config_class' => \App\Modules\Competition\Configs\WSL2Config::class,
            ]
        ],

        // Women's FA Cup, Women's League Cup and Women's Community Shield. Each cup
        // starts at the round the WSL joins, because only the top flight is playable:
        // the qualifying rounds contain nobody the user can be. That also
        // means every club enters at round 1, with each round's field
        // halving cleanly and no entryRound needed anywhere.
        'domestic_cups' => [
            'ENGCUP' => [
                'from_season' => '2026',
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\KnockoutCupConfig::class,
                // No draw_pairing — an open draw is what the Women's FA Cup does.
                // CrossCategoryPairing would make a Premier League tie
                // impossible in the third round, since every playable club
                // is tier 1 and every ghost tier 99.
                'short_name' => "Women's FA Cup",
                'abbreviation' => 'FA',
                'neutral_venues' => [
                    'cup.semi_finals' => ['name' => 'Wembley Stadium', 'capacity' => 90000],
                    'cup.final' => ['name' => 'Wembley Stadium', 'capacity' => 90000],
                ],
            ],
            'ENGLC' => [
                'from_season' => '2026',
                'handler' => 'knockout_cup',
                // Its own table, not the shared one: the Women's League Cup pays half
                // the Women's FA Cup at every stage, and its shorter bracket would
                // otherwise start it partway up the generic scale.
                'config_class' => \App\Modules\Competition\Configs\EflCupConfig::class,
                'short_name' => "Women's League Cup",
                'abbreviation' => 'EFL',
                'neutral_venues' => [
                    'cup.final' => ['name' => 'Wembley Stadium', 'capacity' => 90000],
                ],
            ],
        ],

        // Only the top flight is playable, so tier 1 auto-qualifies and every
        // other entrant is a ghost preserved from the data file. No
        // target_size: with no second tier there is nothing to backfill from,
        // so it could only turn a shortfall into a thrown season transition
        // — a stuck save — rather than repair anything.
        'cup_qualification' => [
            'ENGCUP' => [
                'auto_qualify_tiers' => [1],
            ],
            'ENGLC' => [
                'auto_qualify_tiers' => [1],
            ],
        ],

        'promotions' => [
            [
                // WSL ↔ WSL2. The 14th goes down directly and the champion
                // comes up directly; the 13th faces the WSL2 runners-up in
                // the ENGPO single-match playoff (hosted by the WSL2 team).
                // If the WSL2 team wins, they go up and the 13th goes down;
                // if the WSL team wins, the status quo holds.
                'top_division' => 'ENG1',
                'bottom_division' => 'ENG2',
                'relegated_positions' => [14],
                'relegation_playoff_position' => 13,
                'direct_count' => 1,
                'playoff_count' => 0,
                'relegation_playoff' => true,
                'playoff_competition' => 'ENGPO',
                'playoff_generator' => \App\Modules\Competition\Playoffs\WSLRelegationPlayoffGenerator::class,
                'playoff_trigger_divisions' => ['ENG1', 'ENG2'],
            ],
        ],

        // WSL relegation playoff: ENG2 runners-up vs ENG1 13th place.
        // Bare knockout competition seeded by SeedReferenceData; the
        // bracket itself is generated by WSLRelegationPlayoffGenerator
        // when the ENG2 regular season ends.
        'promotion_playoffs' => [
            'ENGPO' => ['parent_tier' => 1, 'handler' => 'knockout_cup', 'name' => 'WSL Relegation Playoff'],
        ],

        'continental_slots' => [
            'ENG1' => [
                'UCL' => [1],
                'UCLQ' => [2, 3],
                'UEL' => [4],
                'UELQ' => [5],
            ],
        ],

        // Women's cups grant no European place.
        'cup_winner_slot' => [],

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UCLQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
            'UELQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'POR1' => ['role' => 'league', 'handler' => 'league', 'country' => 'PT', 'from_season' => '2026'],
                'NED1' => ['role' => 'league', 'handler' => 'league', 'country' => 'NL', 'from_season' => '2026'],
                'SUI1' => ['role' => 'league', 'handler' => 'league', 'country' => 'CH', 'from_season' => '2026'],
                'FRA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR', 'from_season' => '2026'],
                'ITA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT', 'from_season' => '2026'],
                'DEU2' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE', 'from_season' => '2026'],
                'ENG2' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN', 'from_season' => '2026'],
                'ARG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'AR', 'from_season' => '2026'],
                'BRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'BR', 'from_season' => '2026'],
                'MEX1' => ['role' => 'league', 'handler' => 'league', 'country' => 'MX', 'from_season' => '2026'],
                'USA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'US', 'from_season' => '2026'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UCLQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
                'UELQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'DE' => [
        'name' => 'Deutschland',

        'tiers' => [
            1 => [
                'competition' => 'DEU1',
                'teams' => 14,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\BundesligaConfig::class,
            ],
            2 => [
                'competition' => 'DEU2',
                'teams' => 14,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\ZweiteFrauenBundesligaConfig::class,
            ]
        ],

        // DFB-Pokal Frauen and Supercup Frauen. The Pokal starts at its first round, where
        // all 64 clubs join at once: the Frauen-Bundesliga sides and the ghosts from
        // the divisions below plus the regional cup winners. Only the top
        // flight is playable, so every club enters at round 1, the field
        // halves cleanly six times and no entryRound is needed anywhere.
        'domestic_cups' => [
            'DEUCUP' => [
                'from_season' => '2026',
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\KnockoutCupConfig::class,
                // No draw_pairing. The real Pokal splits its first two rounds
                // into a professional and an amateur pot, but the engine uses
                // one pairing strategy for every round, so CrossCategoryPairing
                // would also forbid a Bayern v Dortmund final — every playable
                // club is tier 1 and every ghost tier 99.
                'short_name' => 'DFB-Pokal Frauen',
                'abbreviation' => 'Pokal',
                'neutral_venues' => [
                    'cup.final' => ['name' => 'Olympiastadion Berlin', 'capacity' => 74000],
                ],
            ],
            'DEUSUP' => [
                'from_season' => '2026',
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\SupercupConfig::class,
                // Two clubs, so the pairing is never in doubt; seeding it
                // just fixes which of them is listed first.
                'draw_pairing' => \App\Modules\Competition\Services\Draw\SeededBracketPairing::class,
                'short_name' => 'Supercup Frauen',
                'abbreviation' => 'Supercup',
                // No neutral_venues: the Supercup is hosted by the Pokal
                // winner, or by the league runner-up when one club did the
                // double.
            ],
        ],

        // Champion v DFB-Pokal Frauen winner, the two-club shape.
        'supercup' => [
            'competition' => 'DEUSUP',
            'cup' => 'DEUCUP',
            'league' => 'DEU1',
            'teams' => 2,
        ],

        // Only the Bundesliga is playable, so tier 1 auto-qualifies and every
        // other entrant is a ghost preserved from the data file. No
        // target_size: with no second playable tier there is nothing to
        // backfill from, so it could only turn a shortfall into a thrown
        // season transition.
        'cup_qualification' => [
            'DEUCUP' => [
                'auto_qualify_tiers' => [1],
            ],
        ],

        'promotions' => [
            [
                // Frauen-Bundesliga ↔ 2. Frauen-Bundesliga. 13th and 14th go
                // down; the top two of the 2. Bundesliga come up, skipping
                // reserve teams (Frankfurt II, Köln II, Hoffenheim II)
                // whose parent is in the top flight.
                'top_division' => 'DEU1',
                'bottom_division' => 'DEU2',
                'relegated_positions' => [13, 14],
                'direct_count' => 2,
                'playoff_count' => 0,
            ],
        ],

        // Reserve teams that cannot be promoted to the same division as
        // their parent. Maps child transfermarkt_id => parent
        // transfermarkt_id.
        'reserve_teams' => [
            950026 => 104, // Eintracht Frankfurt II → Eintracht Frankfurt
            950036 => 5,   // 1. FC Köln II → 1. FC Köln
            950037 => 18,  // TSG 1899 Hoffenheim II → 1899 Hoffenheim
        ],

        'continental_slots' => [
            'DEU1' => [
                'UCL' => [1],
                'UCLQ' => [2, 3],
                'UEL' => [4],
                'UELQ' => [5],
            ],
        ],

        // Women's cups grant no European place.
        'cup_winner_slot' => [],

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UCLQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
            'UELQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'POR1' => ['role' => 'league', 'handler' => 'league', 'country' => 'PT', 'from_season' => '2026'],
                'NED1' => ['role' => 'league', 'handler' => 'league', 'country' => 'NL', 'from_season' => '2026'],
                'SUI1' => ['role' => 'league', 'handler' => 'league', 'country' => 'CH', 'from_season' => '2026'],
                'FRA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR', 'from_season' => '2026'],
                'ITA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT', 'from_season' => '2026'],
                'DEU2' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE', 'from_season' => '2026'],
                'ENG2' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN', 'from_season' => '2026'],
                'ARG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'AR', 'from_season' => '2026'],
                'BRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'BR', 'from_season' => '2026'],
                'MEX1' => ['role' => 'league', 'handler' => 'league', 'country' => 'MX', 'from_season' => '2026'],
                'USA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'US', 'from_season' => '2026'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UCLQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
                'UELQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'IT' => [
        'name' => 'Italia',

        'tiers' => [
            1 => [
                'competition' => 'ITA1',
                'teams' => 12,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\SerieAConfig::class,
            ],
            2 => [
                'competition' => 'ITA2',
                'teams' => 14,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\SerieBFemminileConfig::class,
            ]
        ],

        // Coppa Italia femminile and Supercoppa Italiana femminile. Unlike England's
        // cups, the Coppa keeps its real shape: lower-division sides play the early
        // rounds and the previous season's top eight Serie A Femminile clubs skip
        // to the round of 16. That bye is what makes a 44-club field halve cleanly, so it is
        // declared as a qualification rule rather than baked into the data.
        'domestic_cups' => [
            'ITACUP' => [
                'from_season' => '2026',
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\KnockoutCupConfig::class,
                // No draw_pairing — the Coppa draws its bracket openly, and
                // CrossCategoryPairing would forbid a Serie A v Serie A tie
                // since every playable club is tier 1 and every ghost tier 99.
                'short_name' => 'Coppa Italia femminile',
                'abbreviation' => 'Coppa',
                'neutral_venues' => [
                    'cup.final' => ['name' => 'Stadio Olimpico', 'capacity' => 70000],
                ],
            ],
            'ITASUP' => [
                'from_season' => '2026',
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\SupercupConfig::class,
                // Two clubs, so the pairing is never in doubt; seeding it
                // just fixes which of them is listed first.
                'draw_pairing' => \App\Modules\Competition\Services\Draw\SeededBracketPairing::class,
                'short_name' => 'Supercoppa Italiana femminile',
                'abbreviation' => 'Supercoppa',
                'neutral_venues' => [
                    '*' => ['name' => 'Al-Awwal Park', 'capacity' => 25000],
                ],
            ],
            'ITAWC' => [
                'from_season' => '2026',
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\KnockoutCupConfig::class,
                // No draw_pairing — open draw like the Coppa Italia.
                // The league cup only ever holds the 12 Serie A Femminile
                // clubs: the top four from last season skip the first round.
                'short_name' => "Serie A Women's Cup",
                'abbreviation' => 'WCup',
            ],
        ],

        // Champion v Coppa Italia femminile winner, the two-club shape.
        'supercup' => [
            'competition' => 'ITASUP',
            'cup' => 'ITACUP',
            'league' => 'ITA1',
            'teams' => 2,
        ],

        // Only Serie A Femminile is playable, so tier 1 auto-qualifies and every other
        // entrant is a ghost preserved from the data file. No target_size:
        // with no second playable tier there is nothing to backfill from, so
        // it could only turn a shortfall into a thrown season transition.
        'cup_qualification' => [
            'ITACUP' => [
                'auto_qualify_tiers' => [1],
                // Serie A joins at the first round proper and the eight best
                // of last season skip on to the round of 16. Both halves are
                // needed: without the rule the byes would hold only for the
                // imported season, and without `default` the other twelve
                // would drop to the preliminary round and the field would
                // stop halving.
                'entry_rounds' => [
                    'league' => 'ITA1',
                    'default' => 2,
                    'byes' => [
                        'positions' => [1, 2, 3, 4, 5, 6, 7, 8],
                        'round' => 4,
                    ],
                ],
            ],
            // The league cup is Serie A only: every club qualifies, the top
            // four skip the first round.
            'ITAWC' => [
                'auto_qualify_tiers' => [1],
                'entry_rounds' => [
                    'league' => 'ITA1',
                    'default' => 1,
                    'byes' => [
                        'positions' => [1, 2, 3, 4],
                        'round' => 2,
                    ],
                ],
            ],
        ],

        'promotions' => [
            [
                // Serie A ↔ Serie B. The 12th goes down; the Serie B
                // champions come up.
                'top_division' => 'ITA1',
                'bottom_division' => 'ITA2',
                'relegated_positions' => [12],
                'direct_count' => 1,
                'playoff_count' => 0,
            ],
        ],

        'continental_slots' => [
            'ITA1' => [
                'UCL' => [1],
                'UCLQ' => [2],
                'UEL' => [3],
                'UELQ' => [4],
            ],
        ],

        // Women's cups grant no European place.
        'cup_winner_slot' => [],

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UCLQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
            'UELQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'POR1' => ['role' => 'league', 'handler' => 'league', 'country' => 'PT', 'from_season' => '2026'],
                'NED1' => ['role' => 'league', 'handler' => 'league', 'country' => 'NL', 'from_season' => '2026'],
                'SUI1' => ['role' => 'league', 'handler' => 'league', 'country' => 'CH', 'from_season' => '2026'],
                'FRA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR', 'from_season' => '2026'],
                'ITA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT', 'from_season' => '2026'],
                'DEU2' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE', 'from_season' => '2026'],
                'ENG2' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN', 'from_season' => '2026'],
                'ARG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'AR', 'from_season' => '2026'],
                'BRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'BR', 'from_season' => '2026'],
                'MEX1' => ['role' => 'league', 'handler' => 'league', 'country' => 'MX', 'from_season' => '2026'],
                'USA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'US', 'from_season' => '2026'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UCLQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
                'UELQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'FR' => [
        'name' => 'France',

        'tiers' => [
            1 => [
                'competition' => 'FRA1',
                'teams' => 12,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\Ligue1Config::class,
            ],
            2 => [
                'competition' => 'FRA2',
                'teams' => 11,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\SecondeLigueConfig::class,
            ]
        ],

        // Coupe de France féminine and Trophée des Championnes. France has no league cup
        // The Coupe starts at the round of 64, where the Première Ligue joins:
        // everything below it is
        // regional and amateur, so it contains nobody the user can be. Every
        // club therefore enters at round 1 and no entryRound is needed.
        'domestic_cups' => [
            'FRACUP' => [
                'from_season' => '2026',
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\KnockoutCupConfig::class,
                // No draw_pairing — the Coupe's open draw is the point of it,
                // and CrossCategoryPairing would make a Ligue 1 tie impossible
                // with every playable club at tier 1 and every ghost at 99.
                'short_name' => 'Coupe de France féminine',
                'abbreviation' => 'CdF',
                'neutral_venues' => [
                    'cup.final' => ['name' => 'Stade de France', 'capacity' => 80000],
                ],
            ],
            'FRASUP' => [
                'from_season' => '2026',
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\SupercupConfig::class,
                // Two clubs, so the pairing is never in doubt; seeding it
                // just fixes which of them is listed first.
                'draw_pairing' => \App\Modules\Competition\Services\Draw\SeededBracketPairing::class,
                'short_name' => 'Trophée des Championnes',
                'abbreviation' => 'TdC',
                // No neutral_venues: the Trophée moves every year, often
                // abroad and sometimes to a finalist's own ground.
            ],
        ],

        // Champion v Coupe de France féminine winner, the two-club shape.
        'supercup' => [
            'competition' => 'FRASUP',
            'cup' => 'FRACUP',
            'league' => 'FRA1',
            'teams' => 2,
        ],

        // Only the Première Ligue is playable, so tier 1 auto-qualifies and every other
        // entrant is a ghost preserved from the data file. No target_size:
        // with no second playable tier there is nothing to backfill from, so
        // it could only turn a shortfall into a thrown season transition.
        'cup_qualification' => [
            'FRACUP' => [
                'auto_qualify_tiers' => [1],
            ],
        ],

        'promotions' => [
            [
                // Première Ligue ↔ Seconde Ligue. 11th and 12th go down;
                // the top two of the Seconde Ligue come up.
                'top_division' => 'FRA1',
                'bottom_division' => 'FRA2',
                'relegated_positions' => [11, 12],
                'direct_count' => 2,
                'playoff_count' => 0,
            ],
        ],

        'continental_slots' => [
            'FRA1' => [
                'UCL' => [1],
                'UCLQ' => [2, 3],
                'UEL' => [4],
                'UELQ' => [5],
            ],
        ],

        // Women's cups grant no European place.
        'cup_winner_slot' => [],

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UCLQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
            'UELQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'POR1' => ['role' => 'league', 'handler' => 'league', 'country' => 'PT', 'from_season' => '2026'],
                'NED1' => ['role' => 'league', 'handler' => 'league', 'country' => 'NL', 'from_season' => '2026'],
                'SUI1' => ['role' => 'league', 'handler' => 'league', 'country' => 'CH', 'from_season' => '2026'],
                'FRA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR', 'from_season' => '2026'],
                'ITA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT', 'from_season' => '2026'],
                'DEU2' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE', 'from_season' => '2026'],
                'ENG2' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN', 'from_season' => '2026'],
                'ARG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'AR', 'from_season' => '2026'],
                'BRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'BR', 'from_season' => '2026'],
                'MEX1' => ['role' => 'league', 'handler' => 'league', 'country' => 'MX', 'from_season' => '2026'],
                'USA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'US', 'from_season' => '2026'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UCLQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
                'UELQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'PT' => [
        'name' => 'Portugal',
        // Playable from 2026 only: data/2025 has no folder for any of
        // its competitions, and the seeder and validator demand one for
        // every competition they find here.
        'from_season' => '2026',

        'tiers' => [
            1 => [
                'competition' => 'POR1',
                'teams' => 10,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\PrimeiraLigaConfig::class,
            ],
        ],

        // Taça de Portugal Feminina and Supertaça Feminina — two competitions. The Taça
        // is trimmed to its third round, where the Liga BPI joins: 64 clubs, everything
        // below regional or
        // amateur, so every club enters at round 1 and no entryRound is
        // needed.
        'domestic_cups' => [
            'PORCUP' => [
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\KnockoutCupConfig::class,
                // No draw_pairing — the Taça's open draw is the point of it,
                // and CrossCategoryPairing would make a Primeira Liga tie
                // impossible with every playable club at tier 1 and every
                // ghost at 99.
                'short_name' => 'Taça de Portugal Feminina',
                'abbreviation' => 'Taça',
                'neutral_venues' => [
                    'cup.final' => ['name' => 'Estádio Nacional', 'capacity' => 37000],
                ],
            ],
            'PORSUP' => [
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\SupercupConfig::class,
                // Two clubs, so the pairing is never in doubt; seeding it
                // just fixes which of them is listed first.
                'draw_pairing' => \App\Modules\Competition\Services\Draw\SeededBracketPairing::class,
                'short_name' => 'Supertaça Feminina',
                'abbreviation' => 'Supertaça',
                'neutral_venues' => [
                    '*' => ['name' => 'Estádio Municipal de Aveiro', 'capacity' => 30000],
                ],
            ],
            'PORLC' => [
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\KnockoutCupConfig::class,
                // No draw_pairing — open draw like the Taça de Portugal.
                // The league cup only ever holds the 10 Liga BPI clubs: the
                // top six from last season skip the first round.
                'short_name' => 'Taça da Liga Feminina',
                'abbreviation' => 'Liga',
            ],
        ],

        // Champion v Taça de Portugal Feminina winner, the two-club shape.
        'supercup' => [
            'competition' => 'PORSUP',
            'cup' => 'PORCUP',
            'league' => 'POR1',
            'teams' => 2,
        ],

        // Only the Liga BPI is playable, so tier 1 auto-qualifies and
        // every other entrant is a ghost preserved from the data file. No
        // target_size: with no second playable tier there is nothing to
        // backfill from, so it could only turn a shortfall into a thrown
        // season transition.
        'cup_qualification' => [
            'PORCUP' => [
                'auto_qualify_tiers' => [1],
            ],
            // The league cup is Liga BPI only: every club qualifies, the
            // top six skip the first round.
            'PORLC' => [
                'auto_qualify_tiers' => [1],
                'entry_rounds' => [
                    'league' => 'POR1',
                    'default' => 1,
                    'byes' => [
                        'positions' => [1, 2, 3, 4, 5, 6],
                        'round' => 2,
                    ],
                ],
            ],
        ],

        'promotions' => [],

        'continental_slots' => [
            'POR1' => [
                'UCL' => [1],
                'UCLQ' => [2],
                'UEL' => [3],
            ],
        ],

        // Women's cups grant no European place.
        'cup_winner_slot' => [],

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UCLQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
            'UELQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'NED1' => ['role' => 'league', 'handler' => 'league', 'country' => 'NL', 'from_season' => '2026'],
                'SUI1' => ['role' => 'league', 'handler' => 'league', 'country' => 'CH', 'from_season' => '2026'],
                'FRA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR', 'from_season' => '2026'],
                'ITA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT', 'from_season' => '2026'],
                'DEU2' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE', 'from_season' => '2026'],
                'ENG2' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN', 'from_season' => '2026'],
                'ARG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'AR', 'from_season' => '2026'],
                'BRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'BR', 'from_season' => '2026'],
                'MEX1' => ['role' => 'league', 'handler' => 'league', 'country' => 'MX', 'from_season' => '2026'],
                'USA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'US', 'from_season' => '2026'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UCLQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
                'UELQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'NL' => [
        'name' => 'Países Bajos',
        // Playable from 2026 only: data/2025 has no folder for any of
        // its competitions, and the seeder and validator demand one for
        // every competition they find here.
        'from_season' => '2026',

        'tiers' => [
            1 => [
                'competition' => 'NED1',
                'teams' => 10,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\EredivisieConfig::class,
            ],
        ],

        // KNVB Beker vrouwen and Johan Cruijff Schaal vrouwen. The Beker keeps its real
        // shape: the six Eredivisie Vrouwen clubs playing in Europe sit out the
        // first round, which is what makes a 58-club field halve — 52 in
        // round one, then 26 winners plus the six for a round of 32.
        'domestic_cups' => [
            'NEDCUP' => [
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\KnockoutCupConfig::class,
                // No draw_pairing — the Beker's open draw is the point of
                // it, and CrossCategoryPairing would make an Eredivisie tie
                // impossible with every playable club at tier 1 and every
                // ghost at 99.
                'short_name' => 'KNVB Beker vrouwen',
                'abbreviation' => 'Beker',
                'neutral_venues' => [
                    'cup.final' => ['name' => 'De Kuip', 'capacity' => 47000],
                ],
            ],
            'NEDSUP' => [
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\SupercupConfig::class,
                // Two clubs, so the pairing is never in doubt; seeding it
                // just fixes which of them is listed first.
                'draw_pairing' => \App\Modules\Competition\Services\Draw\SeededBracketPairing::class,
                'short_name' => 'Johan Cruijff Schaal vrouwen',
                'abbreviation' => 'Schaal',
                'neutral_venues' => [
                    '*' => ['name' => 'Johan Cruijff ArenA', 'capacity' => 55000],
                ],
            ],
        ],

        // Champion v KNVB Beker vrouwen winner, the two-club shape.
        'supercup' => [
            'competition' => 'NEDSUP',
            'cup' => 'NEDCUP',
            'league' => 'NED1',
            'teams' => 2,
        ],

        // Only the Eredivisie Vrouwen is playable, so tier 1 auto-qualifies and
        // every other entrant is a ghost preserved from the data file. No
        // target_size: with no second playable tier there is nothing to
        // backfill from, so it could only turn a shortfall into a thrown
        // season transition.
        'cup_qualification' => [
            'NEDCUP' => [
                'auto_qualify_tiers' => [1],
                // The real bye belongs to the clubs playing European
                // football, which the league table can only approximate:
                // the top six covers the five league places below plus a
                // cup winner from among them most seasons. Six is what
                // parity needs — 26 first-round winners have to meet an
                // even field — so the rule fixes the count rather than
                // chasing the exact clubs.
                'entry_rounds' => [
                    'league' => 'NED1',
                    'default' => 1,
                    'byes' => ['positions' => [1, 2, 3, 4, 5, 6], 'round' => 2],
                ],
            ],
        ],

        'promotions' => [],

        'continental_slots' => [
            'NED1' => [
                'UCL' => [1],
                'UCLQ' => [2],
                'UEL' => [3],
            ],
        ],

        // Women's cups grant no European place.
        'cup_winner_slot' => [],

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UCLQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
            'UELQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'POR1' => ['role' => 'league', 'handler' => 'league', 'country' => 'PT', 'from_season' => '2026'],
                'SUI1' => ['role' => 'league', 'handler' => 'league', 'country' => 'CH', 'from_season' => '2026'],
                'FRA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR', 'from_season' => '2026'],
                'ITA2' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT', 'from_season' => '2026'],
                'DEU2' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE', 'from_season' => '2026'],
                'ENG2' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN', 'from_season' => '2026'],
                'ARG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'AR', 'from_season' => '2026'],
                'BRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'BR', 'from_season' => '2026'],
                'MEX1' => ['role' => 'league', 'handler' => 'league', 'country' => 'MX', 'from_season' => '2026'],
                'USA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'US', 'from_season' => '2026'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UCLQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
                'UELQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'CH' => [
        'name' => 'Suiza',
        // Playable from 2026 only: data/2025 has no folder for any of
        // its competitions, and the seeder and validator demand one for
        // every competition they find here.
        'from_season' => '2026',

        'tiers' => [
            1 => [
                'competition' => 'SUI1',
                'teams' => 10,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\SwissSuperLeagueConfig::class,
            ],
        ],

        'promotions' => [],

        'continental_slots' => [
            'SUI1' => [
                'UCL' => [1],
            ],
        ],

        // Women's cups grant no European place.
        'cup_winner_slot' => [],

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UCLQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
            'UELQ' => [
                'config_class' => \App\Modules\Competition\Configs\QualifyingPlayoffConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'POR1' => ['role' => 'league', 'handler' => 'league', 'country' => 'PT', 'from_season' => '2026'],
                'NED1' => ['role' => 'league', 'handler' => 'league', 'country' => 'NL', 'from_season' => '2026'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UCLQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
                'UELQ' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'AR' => [
        'name' => 'Argentina',
        'from_season' => '2026',

        'tiers' => [
            1 => [
                'competition' => 'ARG1',
                'teams' => 16,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\ArgentinePrimeraConfig::class,
            ],
        ],

        'promotions' => [],

        'continental_slots' => [
            'ARG1' => [
                'LIBERTADORES' => [1, 2],
            ],
        ],

        'cup_winner_slot' => [],

        'continental_competitions' => [
            'LIBERTADORES' => [
                'config_class' => \App\Modules\Competition\Configs\LibertadoresConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'BRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'BR', 'from_season' => '2026'],
                'LIBERTADORES' => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'CS'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'LIBERTADORES' => ['handler' => 'knockout_cup', 'country' => 'CS'],
            ],
        ],
    ],

    'BR' => [
        'name' => 'Brasil',
        'from_season' => '2026',

        'tiers' => [
            1 => [
                'competition' => 'BRA1',
                'teams' => 18,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\BrasileiraoFemininoConfig::class,
            ],
        ],

        'promotions' => [],

        'continental_slots' => [
            'BRA1' => [
                'LIBERTADORES' => [1, 2, 3, 4],
            ],
        ],

        'cup_winner_slot' => [],

        'continental_competitions' => [
            'LIBERTADORES' => [
                'config_class' => \App\Modules\Competition\Configs\LibertadoresConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'ARG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'AR', 'from_season' => '2026'],
                'LIBERTADORES' => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'CS'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'LIBERTADORES' => ['handler' => 'knockout_cup', 'country' => 'CS'],
            ],
        ],
    ],

    'MX' => [
        'name' => 'México',
        'from_season' => '2026',

        'tiers' => [
            1 => [
                'competition' => 'MEX1',
                'teams' => 18,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\LigaMXFemenilConfig::class,
            ],
        ],

        'promotions' => [],

        'continental_slots' => [
            'MEX1' => [
                'CONCACHAMPIONS' => [1, 2, 3],
            ],
        ],

        'cup_winner_slot' => [],

        'continental_competitions' => [
            'CONCACHAMPIONS' => [
                'config_class' => \App\Modules\Competition\Configs\ConcachampionsConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'USA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'US', 'from_season' => '2026'],
                'CONCACHAMPIONS' => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'CC'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'CONCACHAMPIONS' => ['handler' => 'knockout_cup', 'country' => 'CC'],
            ],
        ],
    ],

    'US' => [
        'name' => 'Estados Unidos',
        'from_season' => '2026',

        'tiers' => [
            1 => [
                'competition' => 'USA1',
                'teams' => 16,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\NWSLConfig::class,
            ],
        ],

        'promotions' => [],

        'continental_slots' => [
            'USA1' => [
                'CONCACHAMPIONS' => [1, 2, 3],
            ],
        ],

        'cup_winner_slot' => [],

        'continental_competitions' => [
            'CONCACHAMPIONS' => [
                'config_class' => \App\Modules\Competition\Configs\ConcachampionsConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'MEX1' => ['role' => 'league', 'handler' => 'league', 'country' => 'MX', 'from_season' => '2026'],
                'CONCACHAMPIONS' => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'CC'],
                'INT'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'XX'],
            ],
            'continental' => [
                'CONCACHAMPIONS' => ['handler' => 'knockout_cup', 'country' => 'CC'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Women's World Cup qualifying (beta)
    |--------------------------------------------------------------------------
    |
    | One qualifier competition per FIFA confederation, plus WWCQ as the
    | legacy alias (a single global competition in beta saves). The
    | 'tournament' flag keeps this block out of career mode
    | (playableCountryCodes requires tiers). These entries only exist so
    | CountryConfig::configClassForCompetition() resolves the qualifier ids
    | to WorldCupQualifyingConfig; all participants/teams come from the
    | database seeds, not from this file.
    */
    'WQC' => [
        'name' => 'Clasificación Mundial',
        'tournament' => true,
        'continental_competitions' => [
            'WQUEFA' => ['config_class' => \App\Modules\Competition\Configs\WorldCupQualifyingConfig::class],
            'WQAFC'  => ['config_class' => \App\Modules\Competition\Configs\WorldCupQualifyingConfig::class],
            'WQCAF'  => ['config_class' => \App\Modules\Competition\Configs\WorldCupQualifyingConfig::class],
            'WQCONC' => ['config_class' => \App\Modules\Competition\Configs\WorldCupQualifyingConfig::class],
            'WQCONM' => ['config_class' => \App\Modules\Competition\Configs\WorldCupQualifyingConfig::class],
            'WQOFC'  => ['config_class' => \App\Modules\Competition\Configs\WorldCupQualifyingConfig::class],
            // Legacy beta id: the original single global qualifier.
            'WWCQ'   => ['config_class' => \App\Modules\Competition\Configs\WorldCupQualifyingConfig::class],
            // UEFA Women's Nations League (real 2025 groups).
            'WNL'    => ['config_class' => \App\Modules\Competition\Configs\WomensNationsLeagueConfig::class],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Other women's national-team tournaments
    |--------------------------------------------------------------------------
    |
    | Continental championships, same 'tournament' treatment as the WQ*
    | qualifiers above: they only exist so
    | CountryConfig::configClassForCompetition() resolves the ids;
    | participants come from the database seeds, not from this file.
    */
    'WNT' => [
        'name' => 'Torneos de selecciones',
        'tournament' => true,
        'continental_competitions' => [
            'WWCU27'  => ['config_class' => \App\Modules\Competition\Configs\WomensWorldCupConfig::class],
            'WEURO'   => ['config_class' => \App\Modules\Competition\Configs\WomensEuroConfig::class],
            'WEUROQ'  => ['config_class' => \App\Modules\Competition\Configs\WomensEuroQualifyingConfig::class],
            'WCOPAAM' => ['config_class' => \App\Modules\Competition\Configs\CopaAmericaFemeninaConfig::class],
        ],
    ],

];
