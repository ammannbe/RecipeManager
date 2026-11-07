import Alpine from 'alpinejs';

window.Alpine = Alpine;

const desktopQuery = window.matchMedia('(min-width: 48rem)');

// Page scroll lock for overlays; counted so nested overlays do not unlock too early.
let scrollLocks = 0;

const lockScroll = (locked) => {
    scrollLocks = Math.max(0, scrollLocks + (locked ? 1 : -1));
    document.documentElement.classList.toggle('overflow-hidden', scrollLocks > 0);
};

const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

// Keeps Tab / Shift+Tab inside the container (no Alpine focus plugin installed).
const trapFocus = (event, container) => {
    if (event.key !== 'Tab') {
        return;
    }

    const focusable = [...container.querySelectorAll(focusableSelector)].filter((element) => element.offsetParent !== null);

    if (focusable.length === 0) {
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (! event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

const themeToggle = () => ({
    dark: document.documentElement.classList.contains('dark'),

    toggle() {
        this.dark = ! this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        localStorage.setItem('theme', this.dark ? 'dark' : 'light');
    },
});

Alpine.data('themeToggle', themeToggle);

// Public navigation: theme toggle plus the mobile menu panel.
Alpine.data('siteNav', () => ({
    ...themeToggle(),
    open: false,

    init() {
        desktopQuery.addEventListener('change', () => this.close(false));
    },

    show() {
        if (this.open) {
            return;
        }

        this.open = true;
        lockScroll(true);
        this.$nextTick(() => this.$refs.panel.querySelector(focusableSelector)?.focus());
    },

    close(restoreFocus = true) {
        if (! this.open) {
            return;
        }

        this.open = false;
        lockScroll(false);

        if (restoreFocus) {
            this.$refs.toggle.focus();
        }
    },

    trap(event) {
        if (this.open) {
            trapFocus(event, this.$root);
        }
    },
}));

// Recipe index filters: inline and instant on desktop, a bottom sheet applied on demand on phones.
Alpine.data('recipeFilters', ({ expanded }) => ({
    expanded,
    sheetOpen: false,

    init() {
        desktopQuery.addEventListener('change', () => this.closeSheet(false));

        const form = this.$refs.form;
        let searchTimer;

        // Clean URLs: leave out empty fields and the default sort, like the chip links.
        form.addEventListener('formdata', (event) => {
            for (const [name, value] of [...event.formData.entries()]) {
                if (value === '' || (name === 'sort' && value === 'created_at_desc')) {
                    event.formData.delete(name);
                }
            }
        });

        this.$refs.panel.querySelectorAll('select, input').forEach((field) => {
            field.addEventListener('change', () => {
                if (desktopQuery.matches) {
                    form.requestSubmit();
                }
            });
        });

        this.$refs.search.addEventListener('input', () => {
            clearTimeout(searchTimer);

            if (desktopQuery.matches) {
                searchTimer = window.setTimeout(() => form.requestSubmit(), 800);
            }
        });
    },

    openSheet() {
        this.sheetOpen = true;
        lockScroll(true);
        this.$nextTick(() => this.$refs.close.focus());
    },

    // Without applying, the fields go back to the values of the current URL.
    closeSheet(restoreFocus = true) {
        if (! this.sheetOpen) {
            return;
        }

        this.sheetOpen = false;
        lockScroll(false);
        this.restoreFields();

        if (restoreFocus) {
            this.$refs.opener.focus();
        }
    },

    restoreFields() {
        this.$refs.panel.querySelectorAll('input').forEach((input) => {
            input.checked = input.defaultChecked;
        });

        this.$refs.panel.querySelectorAll('option').forEach((option) => {
            option.selected = option.defaultSelected;
        });
    },

    // Clears the selection in the sheet; applied with "Show recipes".
    clearFields() {
        this.$refs.panel.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            input.checked = false;
        });

        this.$refs.panel.querySelectorAll('input[type="radio"]').forEach((input) => {
            input.checked = input.value === '';
        });

        this.$refs.panel.querySelectorAll('select').forEach((select) => {
            select.selectedIndex = 0;
        });
    },

    trap(event) {
        if (this.sheetOpen) {
            trapFocus(event, this.$refs.panel);
        }
    },
}));

// The browser's regional variant of the page language (de-CH writes 1.5, de-DE 1,5), otherwise
// the page language. Mirrors SetLocale::numberLocale().
function numberLocale() {
    const lang = document.documentElement.lang || 'en';
    const language = lang.slice(0, 2).toLowerCase();
    const preferred = (navigator.languages?.length ? navigator.languages : [navigator.language])
        .find((candidate) => candidate && candidate.slice(0, 2).toLowerCase() === language);

    return preferred || lang;
}

// Display modes of the recipe page's amounts, see UnitConverter::pageConversion().
const UNIT_MODE_KEY = 'recipeUnitMode';
// UnitConverter::CUP_FRACTIONS and SPOON_FRACTIONS.
const CUP_FRACTIONS = [0, 1 / 4, 1 / 3, 1 / 2, 2 / 3, 3 / 4];
const SPOON_FRACTIONS = [0, 1 / 8, 1 / 4, 1 / 2, 3 / 4];
const FRACTION_GLYPHS = [[1 / 8, '⅛'], [1 / 4, '¼'], [1 / 3, '⅓'], [1 / 2, '½'], [2 / 3, '⅔'], [3 / 4, '¾']];

// The nearest whole number plus one of the fractions (ties go to the smaller one), like UnitConverter::fraction().
function roundToFraction(value, fractions) {
    const whole = Math.floor(value);
    let best = whole;

    for (const candidate of [...fractions.map((fraction) => whole + fraction), whole + 1]) {
        if (Math.abs(candidate - value) < Math.abs(best - value)) {
            best = candidate;
        }
    }

    return best;
}

function storedUnitMode() {
    try {
        return localStorage.getItem(UNIT_MODE_KEY);
    } catch {
        return null;
    }
}

Alpine.data('recipeServings', ({ initial, modes = ['original'], systems = {} }) => ({
    baseServings: initial,
    servings: initial,
    changed: false,
    // 'original', 'metric' (g/kg or ml/dl/l), 'volume' (ml/dl/l) or a cup mode ('cups', 'cups_us'),
    // chosen in the select and remembered in the browser. Only modes that change something are offered.
    mode: modes.includes(storedUnitMode()) ? storedUnitMode() : 'original',
    // Cup, tablespoon and teaspoon unit per cup mode (UnitConverter::cupSystems()).
    systems,

    get converted() {
        return this.mode !== 'original';
    },

    setMode(mode) {
        this.mode = mode;

        try {
            localStorage.setItem(UNIT_MODE_KEY, mode);
        } catch {
            // Not remembered (storage blocked).
        }
    },

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

        return new Intl.NumberFormat(numberLocale(), {
            maximumFractionDigits: 2,
        }).format(value);
    },

    // conversion: UnitConverter::pageConversion() ({ metric: { measure, factor } | null, metric_changes,
    // volume: ml per unit | null, volume_changes }) or null.
    formatAmount(amount, amountMax, unit, conversion = null) {
        return this.modeAmount(amount, amountMax, conversion) ?? this.originalAmount(amount, amountMax, unit);
    },

    // The amount in the chosen mode, or null when it stays as entered.
    modeAmount(amount, amountMax, conversion) {
        if (! conversion || ! this.converted || (amount === null && amountMax === null)) {
            return null;
        }

        if (this.mode === 'metric') {
            return conversion.metric && conversion.metric_changes
                ? this.convertedAmount(amount, amountMax, conversion.metric)
                : null;
        }

        if (conversion.volume === null) {
            return null;
        }

        if (this.mode === 'volume') {
            return conversion.volume_changes
                ? this.convertedAmount(amount, amountMax, { measure: 'volume', factor: conversion.volume })
                : null;
        }

        return this.systems[this.mode] ? this.cupAmount(amount, amountMax, conversion.volume, this.systems[this.mode]) : null;
    },

    // The recipe's amount in brackets next to a converted one, '' when nothing changed.
    bracketAmount(amount, amountMax, unit, conversion) {
        const converted = this.modeAmount(amount, amountMax, conversion);
        const original = this.originalAmount(amount, amountMax, unit);

        return converted !== null && converted !== original ? '(' + original + ')' : '';
    },

    // The chosen mode cannot convert this amount (no density or piece weight): it stays with a hint.
    cannotConvert(conversion) {
        if (! conversion || ! this.converted) {
            return false;
        }

        return (this.mode === 'metric' ? conversion.metric : conversion.volume) === null;
    },

    // Cup from ¼ cup, otherwise tablespoon from 1, otherwise teaspoon, in friendly fractions.
    // Mirrors UnitConverter::cups(); null when it rounds to nothing.
    cupAmount(amount, amountMax, ml, system) {
        const scale = this.multiplier * ml;
        const base = [amount, amountMax].map((value) => (value !== null ? value * scale : null));
        const reference = base[1] ?? base[0] ?? 0;

        let key = 'teaspoon';

        if (reference >= system.cup.ml / 4) {
            key = 'cup';
        } else if (reference >= system.spoon.ml) {
            key = 'spoon';
        }

        const unit = system[key];
        const fractions = key === 'cup' ? CUP_FRACTIONS : SPOON_FRACTIONS;
        const values = base
            .filter((value) => value !== null)
            .map((value) => roundToFraction(value / unit.ml, fractions));
        const shown = values[values.length - 1];

        if (shown === 0) {
            return null;
        }

        return values.map((value) => this.formatFraction(value)).join(' - ') + ' ' + this.matchingUnitName(unit, shown);
    },

    // 1.5 → 1½, 0.25 → ¼; the whole part in the browser's number format.
    formatFraction(value) {
        const whole = Math.floor(value + 0.000001);
        const glyph = FRACTION_GLYPHS.find(([fraction]) => Math.abs(value - whole - fraction) < 0.001)?.[1] ?? '';

        return (whole > 0 || ! glyph ? this.formatNumber(whole) : '') + glyph;
    },

    // The recipe's amount, shown next to a converted one.
    originalAmount(amount, amountMax, unit) {
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

    // Same units and rounding as UnitConverter::display().
    convertedAmount(amount, amountMax, conversion) {
        const scale = this.multiplier * conversion.factor;
        const base = [amount, amountMax].map((value) => (value !== null ? value * scale : null));
        const reference = base[1] ?? base[0] ?? 0;

        let unit = conversion.measure === 'mass' ? 'g' : 'ml';
        let divisor = 1;

        if (reference >= 1000) {
            [unit, divisor] = [conversion.measure === 'mass' ? 'kg' : 'l', 1000];
        } else if (conversion.measure === 'volume' && reference >= 100) {
            [unit, divisor] = ['dl', 100];
        }

        const round = (value) => {
            const shown = value / divisor;
            const factor = divisor === 1 ? (shown < 10 ? 10 : 1) : 100;

            return Math.round(shown * factor) / factor;
        };

        return base
            .filter((value) => value !== null)
            .map((value) => this.formatNumber(round(value)))
            .join(' - ') + ' ' + unit;
    },

    matchingUnitName(unit, amount) {
        if (amount === null || amount >= 1 || amount === 0) {
            return unit.namePluralShortcut ?? unit.nameShortcut ?? unit.namePlural ?? unit.name;
        }

        return unit.nameShortcut ?? unit.namePluralShortcut ?? unit.name ?? unit.namePlural;
    },
}));

// Only stick when the element fits into the viewport below its sticky top offset,
// otherwise its lower part could never be scrolled into view.
Alpine.data('stickyWhenFits', () => ({
    fits: false,

    init() {
        const update = () => {
            const top = parseFloat(getComputedStyle(this.$el).top) || 0;

            this.fits = this.$el.offsetHeight + top <= window.innerHeight;
        };

        new ResizeObserver(update).observe(this.$el);
        window.addEventListener('resize', update);
        update();
    },
}));

Alpine.start();
