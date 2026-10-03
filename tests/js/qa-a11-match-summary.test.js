/**
 * Regression test for QA bug A11:
 * `ReferenceError: hasHomeAdvantage is not defined` in buildOpening().
 *
 * The variable was declared in generateMatchSummary() but never passed
 * into the ctx object that buildOpening() destructures. Any league match
 * won by exactly 2 goals (2-0, 3-1, 4-2, ...) fell through every earlier
 * opening branch (not a draw, not goalless, not a blowout with diff >= 3,
 * not a narrow win with diff == 1) and reached the home/away-win opening,
 * where the undeclared `hasHomeAdvantage` threw an uncaught exception and
 * no post-match summary was generated.
 *
 * The fix: generateMatchSummary() now passes `hasHomeAdvantage` inside the
 * ctx given to buildOpening(), which destructures it into scope.
 */
import { describe, it, expect } from 'vitest';
import { generateMatchSummary } from '@/modules/match-summary-generator.js';

const TEMPLATES = {
    summaryOpeningHomeWin: ['El :home ganó :score al :away.'],
    summaryOpeningAwayWin: ['El :away ganó :score en casa del :home.'],
    summaryOpeningBlowout: ['Goleada del :winner por :score.'],
    summaryOpeningNarrowWin: ['El :winner ganó por la mínima (:score).'],
    summaryOpeningGoalless: ['Sin goles entre :home y :away.'],
    summaryOpeningDraw: ['Reparto de puntos: :score.'],
    summaryGoalsTeamFragmentSingle: ['un gol de :scorer para :team'],
    summaryGoalsTeamFragmentMulti: ['los goles de :scorer para :team'],
    summaryGoalsOneTeamSingleScorer: [':scorer marcó el único gol de :team.'],
    summaryGoalsOneTeam: [':scorer firmaron los goles de :team.'],
    summaryGoalsTwoTeamsJoin: [':a; :b.'],
    summaryScorerJoinAnd: 'y',
};

function goal(minute, teamId, playerName) {
    return { type: 'goal', minute, teamId, playerName, gamePlayerId: teamId * 100 + minute };
}

function makeConfig(homeScore, awayScore, goalEvents, overrides = {}) {
    return {
        homeTeamId: 1,
        awayTeamId: 2,
        homeTeamName: 'Valencia',
        awayTeamName: 'Madrid',
        homeArticle: 'el',
        awayArticle: 'el',
        homeScore,
        awayScore,
        venueName: 'Mestalla',
        narrativeTemplates: TEMPLATES,
        allEvents: goalEvents,
        isNeutralVenue: false,
        competitionRole: 'league',
        ...overrides,
    };
}

describe('A11: wins by exactly 2 goals must not throw ReferenceError', () => {
    it('generates a summary for a 2-0 home win', () => {
        const out = generateMatchSummary(makeConfig(2, 0, [
            goal(23, 1, 'Pardo'),
            goal(67, 1, 'Tendillo'),
        ]));
        expect(out).toBeTypeOf('string');
        expect(out.length).toBeGreaterThan(0);
        expect(out).toContain('2-0');
    });

    it('generates a summary for a 3-1 home win', () => {
        const out = generateMatchSummary(makeConfig(3, 1, [
            goal(12, 1, 'Pardo'),
            goal(44, 1, 'Tendillo'),
            goal(61, 2, 'Gutierrez'),
            goal(79, 1, 'Pardo'),
        ]));
        expect(out).toBeTypeOf('string');
        expect(out.length).toBeGreaterThan(0);
        expect(out).toContain('3-1');
    });

    it('generates a summary for a 0-2 away win by 2 goals', () => {
        const out = generateMatchSummary(makeConfig(0, 2, [
            goal(35, 2, 'Ferrer'),
            goal(71, 2, 'Lopez'),
        ]));
        expect(out).toBeTypeOf('string');
        expect(out.length).toBeGreaterThan(0);
        expect(out).toContain('0-2');
    });

    it('neutral venue: no home-advantage-only wording for a 2-0, still no throw', () => {
        const out = generateMatchSummary(makeConfig(2, 0, [
            goal(23, 1, 'Pardo'),
            goal(67, 1, 'Tendillo'),
        ], {
            isNeutralVenue: true,
            narrativeTemplates: {
                ...TEMPLATES,
                summaryOpeningHomeWin: ['Victoria del :home por :score.'],
                // Home-only pool must be EXCLUDED at neutral venues
                // (hasHomeAdvantage === false), so this wording can never
                // be picked here.
                summaryOpeningHomeWinHomeOnly: ['Ante su aficion, el :home gano :score.'],
            },
        }));
        expect(out).toBeTypeOf('string');
        expect(out.length).toBeGreaterThan(0);
        expect(out).not.toContain('aficion');
    });

    it('other scorelines still work (narrow win, blowout, draw, goalless)', () => {
        const cases = [
            [1, 0, [goal(88, 1, 'Pardo')], '1-0'],
            [4, 0, [goal(9, 1, 'Pardo'), goal(30, 1, 'Tendillo'), goal(55, 1, 'Pardo'), goal(82, 1, 'Ferrer')], '4-0'],
            [2, 2, [goal(20, 1, 'Pardo'), goal(50, 2, 'Gutierrez'), goal(66, 1, 'Tendillo'), goal(90, 2, 'Ferrer')], '2-2'],
        ];
        for (const [hs, as, events, scoreStr] of cases) {
            const out = generateMatchSummary(makeConfig(hs, as, events));
            expect(out).toBeTypeOf('string');
            expect(out.length).toBeGreaterThan(0);
            expect(out).toContain(scoreStr);
        }
        // Goalless: just needs to produce text without throwing (the opening
        // template does not necessarily include the score).
        const goalless = generateMatchSummary(makeConfig(0, 0, []));
        expect(goalless).toBeTypeOf('string');
        expect(goalless.length).toBeGreaterThan(0);
    });
});
