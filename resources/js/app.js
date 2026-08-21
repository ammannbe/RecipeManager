import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('themeToggle', () => ({
    dark: document.documentElement.classList.contains('dark'),

    toggle() {
        this.dark = ! this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        localStorage.setItem('theme', this.dark ? 'dark' : 'light');
    },
}));

Alpine.data('recipeServings', ({ initial }) => ({
    baseServings: initial,
    servings: initial,
    changed: false,

    get canAdjust() {
        return this.baseServings !== null && this.baseServings > 0;
    },

    get multiplier() {
        return this.canAdjust ? this.servings / this.baseServings : 1;
    },

    increase() {
        if (! this.canAdjust) {
            return;
        }

        this.servings = Math.round((this.servings + 1) * 100) / 100;
        this.changed = true;
    },

    decrease() {
        if (! this.canAdjust || this.servings <= 1) {
            return;
        }

        this.servings = Math.round((this.servings - 1) * 100) / 100;
        this.changed = true;
    },

    formatServings() {
        return this.formatNumber(this.servings);
    },

    formatNumber(value) {
        if (value === null || value === undefined || isNaN(value)) {
            return '';
        }

        return new Intl.NumberFormat(document.documentElement.lang || undefined, {
            maximumFractionDigits: 2,
        }).format(value);
    },

    formatAmount(amount, amountMax, unit) {
        const multiplier = this.multiplier;
        const scaledAmount = amount !== null ? amount * multiplier : null;
        const scaledAmountMax = amountMax !== null ? amountMax * multiplier : null;

        const amountString = [scaledAmount, scaledAmountMax]
            .filter((value) => value !== null)
            .map((value) => this.formatNumber(value))
            .join(' - ');

        const unitName = unit ? this.matchingUnitName(unit, scaledAmountMax ?? scaledAmount) : null;

        return [amountString, unitName].filter(Boolean).join(' ');
    },

    matchingUnitName(unit, amount) {
        if (amount === null || amount >= 1 || amount === 0) {
            return unit.namePluralShortcut ?? unit.nameShortcut ?? unit.namePlural ?? unit.name;
        }

        return unit.nameShortcut ?? unit.namePluralShortcut ?? unit.name ?? unit.namePlural;
    },
}));

Alpine.start();
