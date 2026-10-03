/**
 * Chat de negociación de rescisión de mutuo acuerdo.
 *
 * Flujo: intro → negotiating → payment → done
 * Habla con el endpoint NegotiateMutualTermination (JSON).
 */
export default (negotiateUrl, playerName) => ({
    negotiateUrl,
    playerName,
    openModal: false,
    loading: false,
    step: 'intro', // intro | negotiating | payment | done
    agentMessage: '',
    demandFormatted: '',
    unilateralFormatted: '',
    agreedFormatted: '',
    round: 0,
    maxRounds: 3,
    offerAmount: null,
    paymentMethods: [],
    completeUrl: '',
    error: '',
    terminalOk: false,

    open() {
        this.openModal = true;
        this.step = 'intro';
        this.error = '';
    },

    close() {
        this.openModal = false;
    },

    csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    },

    async post(action, extra = {}) {
        this.loading = true;
        this.error = '';
        try {
            const res = await fetch(this.negotiateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                },
                body: JSON.stringify({ action, ...extra }),
            });
            const data = await res.json();
            if (!res.ok) {
                this.error = data.message || 'Error en la negociación.';
                return null;
            }
            return data;
        } catch (e) {
            this.error = 'Error de conexión.';
            return null;
        } finally {
            this.loading = false;
        }
    },

    async start() {
        const data = await this.post('start');
        if (!data) return;
        this.handleNegotiationResponse(data);
    },

    applyOpenState(data) {
        this.step = 'negotiating';
        this.agentMessage = data.message;
        this.demandFormatted = data.agent_demand_formatted;
        this.unilateralFormatted = data.unilateral_cost_formatted;
        this.round = data.round;
        this.maxRounds = data.max_rounds;
        this.offerAmount = null;
    },

    async sendOffer() {
        if (this.loading) return; // double-click guard: one round per offer
        if (!this.offerAmount && this.offerAmount !== 0) return;
        const data = await this.post('offer', { amount: Math.max(0, Math.round(this.offerAmount)) });
        if (!data) return;
        this.handleNegotiationResponse(data);
    },

    async acceptDemand() {
        const data = await this.post('accept');
        if (!data) return;
        this.handleNegotiationResponse(data);
    },

    async walkAway() {
        const data = await this.post('walk_away');
        if (!data) return;
        this.step = 'done';
        this.terminalOk = false;
        this.agentMessage = data.message;
    },

    handleNegotiationResponse(data) {
        switch (data.negotiation_status) {
            case 'open':
                this.applyOpenState(data);
                break;
            case 'agreed':
                this.step = 'payment';
                this.terminalOk = true;
                this.agentMessage = data.message;
                this.agreedFormatted = data.agreed_amount_formatted;
                this.paymentMethods = data.payment_methods || [];
                this.completeUrl = data.complete_url;
                break;
            case 'rejected':
                this.step = 'done';
                this.terminalOk = false;
                this.agentMessage = data.message;
                break;
            default:
                this.error = data.message || 'Respuesta inesperada.';
        }
    },
});
