<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, SearchX } from '@lucide/vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import ProviderCard from '@/components/catalog/ProviderCard.vue';
import ProviderFilters from '@/components/catalog/ProviderFilters.vue';
import SearchForm from '@/components/catalog/SearchForm.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { filtersToQuery, visitWithFilters } from '@/lib/catalog';
import { plural } from '@/lib/format';
import { search } from '@/routes';
import {
    city as categoryCity,
    show as categoryShow,
} from '@/routes/categories';
import type {
    CatalogFilters,
    CategoryLink,
    CityOption,
    Paginated,
    ProviderCard as ProviderCardType,
    SeoMeta,
    SortOption,
} from '@/types';

// Paieškos rezultatai: tinkančios kategorijos (iš cache) ir teikėjai (FULLTEXT / LIKE).
const props = defineProps<{
    categories: (CategoryLink & { path: string })[];
    providers: Paginated<ProviderCardType>;
    filters: CatalogFilters;
    sortOptions: SortOption[];
    cities: CityOption[];
    seo: SeoMeta;
}>();

const createRequestUrl = '/uzklausos/nauja';

function categoryUrl(slug: string) {
    return props.filters.miestas
        ? categoryCity({ category: slug, city: props.filters.miestas })
        : categoryShow(slug);
}

function applyFilters(next: CatalogFilters): void {
    visitWithFilters(
        search.url({
            query: {
                ...filtersToQuery(next, 'aktualumas'),
                miestas: next.miestas,
            },
        }),
    );
}
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto max-w-6xl px-4 py-8 md:py-12">
        <!-- key: kitai paieškai (kitas q) forma sukuriama iš naujo su naujomis reikšmėmis -->
        <SearchForm
            :key="`${filters.q}|${filters.miestas}`"
            :cities="cities"
            :query="filters.q"
            :city="filters.miestas"
        />

        <h1 class="mt-8 text-2xl font-semibold tracking-tight">
            Paieška: „{{ filters.q }}"
        </h1>

        <section
            v-if="categories.length"
            class="mt-6"
            aria-labelledby="found-categories"
        >
            <h2
                id="found-categories"
                class="text-sm font-medium text-muted-foreground"
            >
                Tinkamos paslaugos
            </h2>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <li v-for="category in categories" :key="category.id">
                    <Link
                        :href="categoryUrl(category.slug)"
                        class="group flex h-full items-center justify-between gap-2 rounded-lg border p-3 transition-colors hover:bg-accent"
                    >
                        <span class="min-w-0">
                            <span
                                class="block font-medium group-hover:underline"
                                >{{ category.name }}</span
                            >
                            <span
                                v-if="category.path"
                                class="block truncate text-xs text-muted-foreground"
                                >{{ category.path }}</span
                            >
                        </span>
                        <ArrowRight
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Link>
                </li>
            </ul>
        </section>

        <section class="mt-10" aria-labelledby="found-providers">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 id="found-providers" class="text-xl font-semibold">
                    Teikėjai
                </h2>
                <p class="text-sm text-muted-foreground">
                    Rasta
                    {{
                        plural(providers.meta.total, [
                            'teikėjas',
                            'teikėjai',
                            'teikėjų',
                        ])
                    }}
                </p>
            </div>

            <ProviderFilters
                class="mt-4"
                :filters="filters"
                :cities="cities"
                :sort-options="sortOptions"
                @change="applyFilters"
            />

            <div v-if="providers.data.length" class="mt-4 grid gap-3">
                <ProviderCard
                    v-for="provider in providers.data"
                    :key="provider.id"
                    :provider="provider"
                />
            </div>
            <div
                v-else
                class="mt-4 flex flex-col items-center gap-3 rounded-lg border border-dashed px-4 py-12 text-center"
            >
                <SearchX
                    class="size-8 text-muted-foreground"
                    aria-hidden="true"
                />
                <p class="font-medium">Teikėjų pagal šią paiešką neradome</p>
                <p class="max-w-md text-sm text-muted-foreground">
                    Pabandykite kitus žodžius arba aprašykite darbą – užklausą
                    pamatys visi tinkami teikėjai.
                </p>
                <Button as-child>
                    <a :href="createRequestUrl">Sukurti užklausą</a>
                </Button>
            </div>

            <CatalogPagination class="mt-6" :paginated="providers" />
        </section>
    </div>
</template>
