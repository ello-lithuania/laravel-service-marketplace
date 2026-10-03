import { router } from '@inertiajs/vue3';
import type { CatalogFilters } from '@/types';

type FilterQuery = Record<string, string | number | null>;

/**
 * Filtrai → URL parametrai. Numatytųjų reikšmių į URL nededam, kad adresai būtų trumpi
 * (/meistrai, o ne /meistrai?patikrinti=0&rikiuoti=reitingas). Miestas – atskirai (žr. puslapius).
 */
export function filtersToQuery(
    filters: CatalogFilters,
    defaultSort = 'reitingas',
): FilterQuery {
    return {
        q: filters.q || null,
        patikrinti: filters.patikrinti ? 1 : null,
        reitingas: filters.reitingas,
        rikiuoti: filters.rikiuoti !== defaultSort ? filters.rikiuoti : null,
    };
}

/**
 * Inertia apsilankymas su naujais filtrais: puslapis neperkraunamas, slinkties vieta išlieka,
 * o naršyklės istorijoje įrašas pakeičiamas (Atgal grąžina į ankstesnį puslapį, ne į kiekvieną filtrą).
 */
export function visitWithFilters(url: string): void {
    router.get(
        url,
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}
