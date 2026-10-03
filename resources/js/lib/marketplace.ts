// Etapas 5: pinigų, datų ir būsenų formatavimas lietuviškai.
// Serveris siunčia pinigus centais ir datas ISO formatu (UTC), čia jas rodom Lietuvos laiku.

import { formatPrice } from '@/lib/format';
import type { ServiceRequestStatus, OfferStatus } from '@/types/marketplace';

const TIME_ZONE = 'Europe/Vilnius';

/** 125000 → „1 250 €" */
export function formatMoney(cents: number): string {
    // Viena formatavimo taisyklė visam projektui: sveiki eurai be centų, kitaip – visada 2 skaitmenys („320,50 €")
    return formatPrice(cents);
}

/** Biudžetas: „100 € – 300 €", „nuo 100 €", „iki 300 €" arba „Nenurodytas" */
export function formatBudget(min: number | null, max: number | null): string {
    if (min !== null && max !== null) {
        return `${formatMoney(min)} – ${formatMoney(max)}`;
    }

    if (min !== null) {
        return `nuo ${formatMoney(min)}`;
    }

    if (max !== null) {
        return `iki ${formatMoney(max)}`;
    }

    return 'Nenurodytas';
}

/** „2026 m. spalio 3 d." */
export function formatDate(iso: string | null): string {
    if (!iso) {
        return '–';
    }

    return new Intl.DateTimeFormat('lt-LT', {
        dateStyle: 'long',
        timeZone: TIME_ZONE,
    }).format(new Date(iso));
}

/** „2026-10-03 14:05" */
export function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '–';
    }

    return new Intl.DateTimeFormat('lt-LT', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: TIME_ZONE,
    }).format(new Date(iso));
}

const relativeFormat = new Intl.RelativeTimeFormat('lt', { numeric: 'auto' });

/** „prieš 5 minutes", „vakar", „po 3 dienų" */
export function timeAgo(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['week', 604_800],
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeFormat.format(Math.round(seconds / size), unit);
        }
    }

    return 'ką tik';
}

/** Ženklelio spalvos pagal būseną (Tailwind klasės) */
export function statusClasses(
    status: ServiceRequestStatus | OfferStatus,
): string {
    switch (status) {
        case 'pending':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
        case 'open':
            return 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300';
        case 'in_progress':
            return 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300';
        case 'completed':
        case 'accepted':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

/** Lietuviška daugiskaita: 1 pasiūlymas, 2 pasiūlymai, 10 pasiūlymų, 21 pasiūlymas */
export function plural(
    count: number,
    one: string,
    few: string,
    many: string,
): string {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return one;
    }

    if (mod10 >= 2 && mod10 <= 9 && (mod100 < 11 || mod100 > 19)) {
        return few;
    }

    return many;
}
