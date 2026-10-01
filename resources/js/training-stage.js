// National-team training camp configurator: pick destination, duration,
// intensity and focus, with a live cost/effects preview. The preview mirrors
// TrainingStageService's formula (home country = cheap, abroad = expensive).
export default function trainingStage(config) {
    return {
        destination: config.initialDestination || '',
        duration: '1w',
        intensity: 'balanced',
        focus: 'physical',
        countries: config.countries || [],
        homeCountry: config.homeCountry || '',
        budget: config.budget || 0,
        durations: config.durations || {},
        intensities: config.intensities || {},
        focuses: config.focuses || {},
        labels: config.labels || {},

        get weeklyBase() {
            return this.destination === this.homeCountry ? 60000 : 100000;
        },

        get cost() {
            const d = this.durations[this.duration];
            const i = this.intensities[this.intensity];
            const f = this.focuses[this.focus];
            if (!d || !i || !f || !this.destination) return 0;
            return Math.round(this.weeklyBase * d.weeks * d.cost_mult * i.cost_mult * f.cost_mult);
        },

        get affordable() {
            return this.destination !== '' && this.cost <= this.budget;
        },

        get effects() {
            const d = this.durations[this.duration];
            const i = this.intensities[this.intensity];
            const f = this.focuses[this.focus];
            if (!d || !i || !f) return [];
            const mult = d.effect_mult;
            const lines = [];
            const fitness = Math.round((i.fitness + f.fitness) * mult);
            const morale = Math.round((i.morale + f.morale) * mult);
            lines.push(this.fmt(this.labels.fitness, fitness >= 0 ? '+' + fitness : String(fitness)));
            lines.push(this.fmt(this.labels.morale, morale >= 0 ? '+' + morale : String(morale)));
            lines.push(this.fmt(this.labels.injury, i.injury_risk + f.injury_risk));
            if (f.youth_boost > 0) {
                lines.push(this.fmt(this.labels.youth, '+' + f.youth_boost));
            }
            return lines;
        },

        fmt(template, value) {
            return (template || '{v}').replace('{v}', value);
        },

        formatMoney(value) {
            return new Intl.NumberFormat(document.documentElement.lang || 'es', {
                style: 'currency',
                currency: 'EUR',
                maximumFractionDigits: 0,
            }).format(value);
        },
    };
}
