<?php

return [
    // ── Matchday gate ──────────────────────────────────────────────────

    // Matchday revenue per seat per season by reputation level (in cents).
    'revenue_per_seat' => [
        'elite'        => 7_000, // €7/seat
        'continental'  => 4_400, // €4.40/seat
        'established'  => 3_100, // €3.10/seat
        'modest'       => 2_100, // €2.10/seat
        'local'        =>   900, // €0.90/seat
    ],

    // ── Season tickets ─────────────────────────────────────────────────

    // Season-ticket pricing presets: a single global multiplier on each area's
    // baseline price. Accessible fills more seats at a lower per-seat price;
    // Premium does the reverse. Discrete presets (not free-number sliders)
    // keep the decision legible and the demand model un-gameable.
    'season_ticket_presets' => [
        'accessible' => 0.75,
        'standard'   => 1.00,
        'premium'    => 1.40,
    ],

    // Preset price elasticity on TOTAL match-day demand (occupancy), separate
    // from how the preset splits abonos vs walk-up. Without this, walk-up just
    // backfills whatever abonos don't take, so the crowd ≈ loyalty demand
    // regardless of price and the preset labels ("más lleno" / "menos ocupación")
    // don't hold. The factor scales the demand curve before walk-up is derived:
    // cheaper prices draw a bigger crowd, premium prices price some fans out.
    // Walk-up VOLUME moves with it; the per-seat gate rate stays fixed.
    'season_ticket_occupancy_factor' => [
        'accessible' => 1.10,
        'standard'   => 1.00,
        'premium'    => 0.85,
    ],

    // Max share of match demand left for walk-up buyers, at zero loyalty.
    // Abono penetration is scaled to (1 − reserve_max × (1 − loyalty/100)) of
    // the loyalty fill, so the walk-up gap is widest for low-loyalty clubs and
    // tapers to ~0 for elites — who legitimately sell out via abonos and so
    // draw little walk-up. This is what stops a club's projected taquilla from
    // collapsing to €0 just because its season-ticket curve matched its
    // attendance curve (they used to be identical).
    'season_ticket_walkup_reserve_max' => 0.15,

    // Share of season-ticket holders who don't attend a given match. Lowers the
    // displayed/settled crowd (attending holders + walk-ups) but does NOT change
    // walk-up gate revenue — abonos are prepaid, so an empty paid seat earns
    // nothing extra and costs nothing.
    'season_ticket_noshow_rate' => 0.05,

    // ── Attendance demand ──────────────────────────────────────────────

    // Secondary floor on stadium occupancy. With the loyalty formula
    // (0.50 + loyalty/100 × 0.45), the natural minimum is 50% at loyalty 0.
    // These floors only trigger for elite/continental clubs whose loyalty
    // has collapsed below the level implied by their reputation — a marquee
    // brand still draws walk-ups and tourists even when the terraces have
    // thinned. For established and below the formula floor is sufficient.
    'reputation_fill_floor' => [
        'elite'        => 0.65, // kicks in at loyalty_points < 34
        'continental'  => 0.60, // kicks in at loyalty_points < 23
        'established'  => 0.55, // kicks in at loyalty_points < 12
        'modest'       => 0.50, // matches formula floor; effectively a no-op
        'local'        => 0.50,
    ],

    // ── Fan loyalty ────────────────────────────────────────────────────

    // Per-event nudges applied to loyalty_points by FanLoyaltyUpdateProcessor
    // at season close. Clamped to [0, 100] after summing; also floored at
    // base_loyalty - MAX_LOYALTY_DROP_BELOW_BASE so loyal clubs stay loyal.
    'loyalty_deltas' => [
        'league_title'        =>  5, // Won the top-tier league
        'cup'                 =>  3, // Per cup victory (CupTie winner)
        'top_four_finish'     =>  1, // Finished 1st-4th in any league
        'bottom_three_finish' => -2, // Finished in the bottom three of any league
        'gravity'             => -1, // Applied unconditionally each season
    ],

    // ── Stadium upgrades ───────────────────────────────────────────────

    // Stadium upgrade pricing. All costs rescaled ÷100 to the women's
    // football economy (SheManagerGame) — real-world construction anchors are
    // cited for reference, but in-game values must stay affordable against
    // ÷100 club revenues or upgrades would be unbuyable.
    'stadium_costs' => [
        // Modular bleachers — temporary feel, fast install. Per-seat cost
        // anchored on real-world temp-stadium contracts (e.g. Ibercaja
        // Estadio for the Zaragoza relocation: ~€6M for ~14k seats).
        'supplementary_per_seat_cents' => 4_000, // €40/seat
        'supplementary_max_seats_per_project' => 8_000,
        // Supplementary stands take this many in-game days to install.
        'supplementary_construction_days' => 30,

        // Permanent single-stand rebuild — Anfield Road / Selhurst Park
        // scope (€30–80M for 3–8k seats ≈ €4–10k/seat). Construction takes
        // a fixed in-game duration; the rest of the stadium stays open
        // during the build (no capacity drop).
        'stand_expansion_per_seat_cents' => 8_000, // €80/seat
        'stand_expansion_min_seats' => 3_000,
        'stand_expansion_max_seats' => 12_000,
        'stand_expansion_construction_days' => 270, // ~9 months / one football season

        // Full rebuild — cumulative bracket pricing (tax-bracket style).
        // Per-seat marginal cost grows with target size; total cost stays
        // continuous as the slider crosses bracket boundaries.
        // Anchors: Boston United / FC Andorra (≤10k, €3k/seat);
        // Wildparkstadion (≤30k, €5k); Europa-Park Stadion (≤50k, €7k);
        // Metropolitano (≤80k, €10k); Wembley / Bernabéu (>80k, €15k).
        'rebuild_per_seat_bands' => [
            ['up_to' =>  10_000, 'per_seat_cents' =>    3_000],
            ['up_to' =>  30_000, 'per_seat_cents' =>    5_000],
            ['up_to' =>  50_000, 'per_seat_cents' =>    7_000],
            ['up_to' =>  80_000, 'per_seat_cents' =>   10_000],
            ['up_to' =>    null, 'per_seat_cents' =>   15_000],
        ],
        'rebuild_construction_days' => 540, // ~18 months / two football seasons

        // UEFA category upgrade — facility-tier renovation (covered seats,
        // floodlights, broadcast booths, media rooms, dressing rooms, etc.)
        // to meet the next UEFA category's infrastructure requirements.
        // One-level-at-a-time; each transition costs the amount listed
        // below (key = source level). No capacity change while the
        // facilities are being fitted out.
        // Reference: real-world UEFA Cat 4 fit-outs run ~€20–80M depending on
        // the starting state; the values here sit at the rescaled (÷100)
        // lower end so multiple projects across a long save remain affordable.
        'uefa_upgrade_cost_cents' => [
            1 => 5_000_000,    // 1 → 2: €50K
            2 => 20_000_000,   // 2 → 3: €200K
            3 => 50_000_000,   // 3 → 4: €500K
        ],
        'uefa_upgrade_construction_days' => 270, // ~9 months / one football season
    ],

    // Stadium rebuild loan configuration.
    // Flat-principal: principal/term_years constant principal per year,
    // plus interest on the outstanding balance — total payment is highest
    // in year 1 and declines over the term.
    'stadium_loan' => [
        'term_years' => 10,
        'interest_rate_bps' => 400,            // 4% interest (basis points)
        // Maximum share of projected operating revenue that can go to
        // year-1 debt service. The bank refuses to lend beyond this.
        'max_debt_service_pct' => 0.25,
        // Reputation-tier ceilings on loan principal (in cents). Ambition cap.
        'reputation_caps' => [
            'local'        =>   100_000_000, // €1M
            'modest'       =>   250_000_000, // €2.5M
            'established'  =>   600_000_000, // €6M
            'continental'  => 1_200_000_000, // €12M
            'elite'        => 2_500_000_000, // €25M
        ],
    ],
];
