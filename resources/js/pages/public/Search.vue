<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, LayoutGrid } from '@lucide/vue';
import ProviderResults from '@/components/catalog/ProviderResults.vue';
import SearchForm from '@/components/catalog/SearchForm.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import { filtersToQuery, visitWithFilters } from '@/lib/catalog';
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

    <section
        class="border-b bg-gradient-to-b from-secondary/70 to-background dark:from-secondary/30"
    >
        <div class="page-container pt-8 pb-10 md:pt-10">
            <p class="text-sm font-medium text-muted-foreground">Paieška</p>
            <h1
                class="mt-2 text-3xl leading-tight font-bold text-balance md:text-4xl"
            >
                „{{ filters.q }}“
            </h1>
            <!-- key: kitai paieškai (kitas q) forma sukuriama iš naujo su naujomis reikšmėmis -->
            <SearchForm
                :key="`${filters.q}|${filters.miestas}`"
                class="mt-6 max-w-3xl"
                :cities="cities"
                :query="filters.q"
                :city="filters.miestas"
            />
        </div>
    </section>

    <div class="page-container pt-10 pb-20">
        <section
            v-if="categories.length"
            class="mb-12"
            aria-labelledby="found-categories"
        >
            <h2 id="found-categories" class="text-xl font-semibold">
                Tinkamos paslaugos
            </h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <li v-for="category in categories" :key="category.id">
                    <Link
                        :href="categoryUrl(category.slug)"
                        class="group flex h-full items-center gap-3 rounded-xl border bg-card p-4 shadow-soft transition-[box-shadow,border-color] hover:border-primary/40 hover:shadow-lift"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary"
                        >
                            <LayoutGrid class="size-4.5" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">{{
                                category.name
                            }}</span>
                            <span
                                v-if="category.path"
                                class="block truncate text-xs text-muted-foreground"
                                >{{ category.path }}</span
                            >
                        </span>
                        <ArrowRight
                            class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                            aria-hidden="true"
                        />
                    </Link>
                </li>
            </ul>
        </section>

        <ProviderResults
            title="Teikėjai"
            :providers="providers"
            :filters="filters"
            :cities="cities"
            :sort-options="sortOptions"
            :create-request-url="createRequestUrl"
            empty-title="Teikėjų pagal šią paiešką neradome"
            empty-text="Pabandykite kitus žodžius arba aprašykite darbą – užklausą pamatys visi tinkami teikėjai."
            @change="applyFilters"
        />
    </div>
</template>
