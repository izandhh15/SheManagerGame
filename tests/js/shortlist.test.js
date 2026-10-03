/**
 * Tests for shortlist.js — the shared shortlist client.
 *
 * The regression we guard against: removeFromShortlist() announced the
 * removal even when the server reported success: false, leaving every
 * shortlist surface out of sync with the backend. It now announces only
 * on success, like toggleShortlist() already did.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { removeFromShortlist, toggleShortlist } from '@/modules/shortlist.js';

function mockServer(payload) {
    globalThis.fetch = vi.fn().mockResolvedValue({
        json: () => Promise.resolve(payload),
    });
}

function captureAnnouncements() {
    const events = [];
    globalThis.window = {
        dispatchEvent: (event) => {
            events.push(event);
            return true;
        },
    };
    globalThis.document = {
        querySelector: () => null,
    };
    // Minimal CustomEvent for the node environment.
    globalThis.CustomEvent = class CustomEvent {
        constructor(type, { detail } = {}) {
            this.type = type;
            this.detail = detail;
        }
    };
    return events;
}

beforeEach(() => {
    vi.restoreAllMocks();
});

describe('removeFromShortlist', () => {
    it('announces the removal when the server confirms success', async () => {
        mockServer({ success: true });
        const events = captureAnnouncements();

        const data = await removeFromShortlist('/shortlist/abc', 'player-1');

        expect(data.success).toBe(true);
        expect(events).toHaveLength(1);
        expect(events[0].type).toBe('shortlist-toggled');
        expect(events[0].detail).toEqual({ action: 'removed', playerId: 'player-1' });
    });

    it('does NOT announce when the server reports failure', async () => {
        mockServer({ success: false, message: 'Not on shortlist' });
        const events = captureAnnouncements();

        const data = await removeFromShortlist('/shortlist/abc', 'player-1');

        expect(data.success).toBe(false);
        expect(events).toHaveLength(0);
    });
});

describe('toggleShortlist', () => {
    it('announces only on success', async () => {
        mockServer({ success: false });
        const events = captureAnnouncements();

        await toggleShortlist('/shortlist/abc');

        expect(events).toHaveLength(0);
    });
});
