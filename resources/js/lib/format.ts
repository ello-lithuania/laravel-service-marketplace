// Lietuviškas skaičių, kainų ir datų formatavimas (Intl API naršyklėje, be papildomų bibliotekų).

const pluralRules = new Intl.PluralRules('lt');

const wholeEuro = new Intl.NumberFormat('lt-LT', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
});

const euroWithCents = new Intl.NumberFormat('lt-LT', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
});

const ratingFormat = new Intl.NumberFormat('lt-LT', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
});

const dateFormat = new Intl.DateTimeFormat('lt-LT', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    timeZone: 'Europe/Vilnius',
});

const monthFormat = new Intl.DateTimeFormat('lt-LT', {
    year: 'numeric',
    month: 'long',
    timeZone: 'Europe/Vilnius',
});

/** Pinigai DB saugomi centais: 1500 → „15 €", 1550 → „15,50 €". */
export function formatPrice(cents: number): string {
    return cents % 100 === 0
        ? wholeEuro.format(cents / 100)
        : euroWithCents.format(cents / 100);
}

/** „nuo 15 € / val." */
export function formatPriceFrom(cents: number, unit: string | null): string {
    return `nuo ${formatPrice(cents)}${unit ? ` / ${unit}` : ''}`;
}

/** 4.666 → „4,7" */
export function formatRating(value: number): string {
    return ratingFormat.format(value);
}

/**
 * Lietuviška daugiskaita: plural(1, ['atsiliepimas', 'atsiliepimai', 'atsiliepimų']) → „1 atsiliepimas",
 * 2–9 → „atsiliepimai", 0 ir 10–20 → „atsiliepimų".
 */
export function plural(
    count: number,
    [one, few, many]: [string, string, string],
): string {
    return `${count} ${pluralWord(count, [one, few, many])}`;
}

/** Tik žodžio forma be skaičiaus: pluralWord(21, ['teikėjas', 'teikėjai', 'teikėjų']) → „teikėjas". */
export function pluralWord(
    count: number,
    [one, few, many]: [string, string, string],
): string {
    const form = pluralRules.select(count);

    return form === 'one' ? one : form === 'few' ? few : many;
}

const integerFormat = new Intl.NumberFormat('lt-LT');

/** 4859 → „4 859" (tūkstančiai atskiriami tarpu, kaip įprasta lietuviškai). */
export function formatNumber(value: number): string {
    return integerFormat.format(value);
}

/** ISO data (UTC) → „2026 m. spalio 3 d." Lietuvos laiku. */
export function formatDate(iso: string): string {
    return dateFormat.format(new Date(iso));
}

/** „2026-06-15" → „2026 m. birželis" */
export function formatMonth(date: string): string {
    return monthFormat.format(new Date(date));
}
