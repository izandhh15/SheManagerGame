/**
 * Tests for lineup.js — the lineup manager's teamAverage getter.
 *
 * The regression we guard against: teamAverage divided the summed overall
 * scores by a hardcoded 11 even when fewer than 11 players were selected,
 * deflating the average that feeds coachTips and the xG preview. It now
 * divides by the actual selected count, like averageFitness already did.
 */
import { describe, it, expect } from 'vitest';
import lineupManager from '@/lineup.js';

function makeManager(selectedCount, overallScore = 80) {
    const playersData = {};
    const ids = [];
    for (let i = 1; i <= selectedCount; i++) {
        const id = `p${i}`;
        ids.push(id);
        playersData[id] = { overallScore, fitness: 90 };
    }
    return lineupManager({
        currentLineup: ids,
        playersData,
        formationSlots: {},
        currentFormation: '4-3-3',
        slotCompatibility: {},
    });
}

describe('teamAverage', () => {
    it('divides by the selected count, not a hardcoded 11', () => {
        const mgr = makeManager(7, 80);

        // 7 × 80 / 7 = 80 — the old code computed Math.round(560 / 11) = 51.
        expect(mgr.teamAverage).toBe(80);
    });

    it('still averages correctly with a full XI', () => {
        const mgr = makeManager(11, 80);

        expect(mgr.teamAverage).toBe(80);
    });

    it('returns 0 with no players selected', () => {
        const mgr = makeManager(0);

        expect(mgr.teamAverage).toBe(0);
    });
});
