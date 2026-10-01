// Pre-season setup wizard: pick a friendly opponent per fixture slot from the
// grouped team list, search within the picker modal, and toggle home/away. Each
// selection is mirrored into hidden inputs in the blade via :value bindings, so
// this component only owns the in-flight choices, not the form submission.
//
// Slots taken by ACCEPTED AI invitations arrive pre-filled and locked: the
// picker and the clear button are hidden for them (the invitation can be
// cancelled with its own form, which reloads the page).
export default function preseasonSetup(config) {
    const accepted = config.accepted || [];

    const selections = Array.from({ length: config.slotCount }, () => ({
        teamId: null,
        teamName: '',
        teamImage: '',
        isHome: true,
        trophyName: '',
        stadiumName: '',
        locked: false,
        invitationId: null,
    }));

    // Lock the slots occupied by accepted invitations.
    for (const inv of accepted) {
        if (inv.slot >= 0 && inv.slot < selections.length) {
            selections[inv.slot] = {
                teamId: inv.teamId,
                teamName: inv.teamName,
                teamImage: inv.teamImage,
                isHome: false,
                trophyName: inv.trophyName || '',
                stadiumName: inv.stadiumName || '',
                locked: true,
                invitationId: inv.invitationId,
            };
        }
    }

    return {
        teams: config.teams || [],
        assetUrl: config.assetUrl,
        selections,
        openSlot: null,
        searchQuery: '',

        get filteredGroups() {
            const q = this.searchQuery.trim().toLowerCase();
            if (!q) return this.teams;
            return this.teams
                .map((g) => ({ ...g, teams: g.teams.filter((t) => t.name.toLowerCase().includes(q)) }))
                .filter((g) => g.teams.length > 0);
        },

        choose(team) {
            const s = this.selections[this.openSlot];
            if (s.locked) {
                this.closeModal();
                return;
            }
            s.teamId = team.id;
            s.teamName = team.name;
            s.teamImage = team.image;
            this.closeModal();
        },

        clear(i) {
            const s = this.selections[i];
            if (s.locked) return;
            s.teamId = null;
            s.teamName = '';
            s.teamImage = '';
            s.isHome = true;
            s.trophyName = '';
            s.stadiumName = '';
        },

        closeModal() {
            this.openSlot = null;
            this.searchQuery = '';
        },
    };
}
