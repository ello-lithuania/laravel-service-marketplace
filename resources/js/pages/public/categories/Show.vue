<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, SearchX } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import CategoryIcon from '@/components/catalog/CategoryIcon.vue';
import ProviderCard from '@/components/catalog/ProviderCard.vue';
import ProviderFilters from '@/components/catalog/ProviderFilters.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { filtersToQuery, visitWithFilters } from '@/lib/catalog';
import { plural } from '@/lib/format';
import { home } from '@/routes';
import {
    city as categoryCity,
    index as categoriesIndex,
    show as categoryShow,
} from '@/routes/categories';
import type {
    BreadcrumbItem,
    CatalogFilters,
    CategoryLink,
    CategoryWithChildren,
    CityOption,
    Paginated,
    ProviderCard as ProviderCardType,
    SeoMeta,
    SortOption,
} from '@/types';

// Vienas komponentas visiems 3 lygiams ir „paslauga mieste" variantui (kai city ne null).
const props = defineProps<{
    category: CategoryLink & {
        depth: number;
        description: string | null;
        icon: string | null;
    };
    breadcrumbs: CategoryLink[];
    subcategories: CategoryWithChildren[];
    city: CityOption | null;
    providers: Paginated<ProviderCardType>;
    filters: CatalogFilters;
    sortOptions: SortOption[];
    cities: CityOption[];
    popularCities: CityOption[];
    seo: SeoMeta;
}>();

// 3 lygio paslaugos puslapyje užklausos forma atsidaro su jau parinkta paslauga (ir miestu)
const createRequestUrl = computed(() => {
    if (props.category.depth !== 3) {
        return '/uzklausos/nauja';
    }

    const query = new URLSearchParams({ kategorija: props.category.slug });

    if (props.city) {
        query.set('miestas', props.city.slug);
    }

    return `/uzklausos/nauja?${query.toString()}`;
});

const heading = computed(() =>
    props.city
        ? `${props.category.name} ${props.city.name_locative}`
        : props.category.name,
);

// Jei pasirinktas miestas, ir subkategorijų nuorodos veda į to miesto puslapius
function categoryUrl(slug: string) {
    return props.city
        ? categoryCity({ category: slug, city: props.city.slug })
        : categoryShow(slug);
}

const breadcrumbItems = computed<BreadcrumbItem[]>(() => {
    const items: BreadcrumbItem[] = [
        { title: 'Pradžia', href: home() },
        { title: 'Paslaugos', href: categoriesIndex() },
        ...props.breadcrumbs.map((item) => ({
            title: item.name,
            href: categoryUrl(item.slug),
        })),
        { title: props.category.name, href: categoryShow(props.category.slug) },
    ];

    if (props.city) {
        items.push({
            title: props.city.name,
            href: categoryUrl(props.category.slug),
        });
    }

    return items;
});

// Miestas – URL dalis (/paslaugos/{kategorija}/{miestas}), kiti filtrai – parametrai (?patikrinti=1)
function applyFilters(next: CatalogFilters): void {
    const query = filtersToQuery(next);

    visitWithFilters(
        next.miestas
            ? categoryCity.url(
                  { category: props.category.slug, city: next.miestas },
                  { query },
              )
            : categoryShow.url(props.category.slug, { query }),
    );
}
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto max-w-6xl px-4 py-8 md:py-12">
        <Breadcrumbs :breadcrumbs="breadcrumbItems" />

        <header
            class="mt-4 flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
        >
            <div class="flex items-start gap-3">
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <CategoryIcon :name="category.icon" class="size-6" />
                </span>
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight md:text-3xl"
                    >
                        {{ heading }}
                    </h1>
                    <p
                        v-if="category.description"
                        class="mt-2 max-w-2xl text-muted-foreground"
                    >
                        {{ category.description }}
                    </p>
                    <p v-else class="mt-2 max-w-2xl text-muted-foreground">
                        Palyginkite meistrų atsiliepimus ir kainas arba
                        aprašykite darbą – tinkami teikėjai patys atsiųs
                        pasiūlymus.
                    </p>
                </div>
            </div>
            <Button size="lg" class="shrink-0" as-child>
                <a :href="createRequestUrl">Sukurti užklausą</a>
            </Button>
        </header>

        <!-- 1 lygis: 2 lygio grupės su 3 lygio paslaugomis -->
        <section
            v-if="category.depth === 1 && subcategories.length"
            class="mt-8"
            aria-label="Paslaugos"
        >
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="group in subcategories"
                    :key="group.id"
                    class="rounded-lg border p-4"
                >
                    <h2 class="font-medium">
                        <Link
                            :href="categoryUrl(group.slug)"
                            class="hover:underline"
                        >
                            {{ group.name }}
                        </Link>
                    </h2>
                    <ul class="mt-2 space-y-1 text-sm text-muted-foreground">
                        <li v-for="leaf in group.children" :key="leaf.id">
                            <Link
                                :href="categoryUrl(leaf.slug)"
                                class="hover:text-foreground hover:underline"
                                >{{ leaf.name }}</Link
                            >
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- 2 lygis: jo paslaugos; 3 lygis: kitos tos pačios grupės paslaugos -->
        <nav
            v-else-if="subcategories.length"
            class="mt-6 flex flex-wrap gap-2"
            aria-label="Susijusios paslaugos"
        >
            <Link
                v-for="item in subcategories"
                :key="item.id"
                :href="categoryUrl(item.slug)"
                :aria-current="item.id === category.id ? 'page' : undefined"
                class="rounded-full border px-3 py-1 text-sm transition-colors hover:bg-accent aria-[current=page]:border-primary aria-[current=page]:bg-primary aria-[current=page]:text-primary-foreground"
                >{{ item.name }}</Link
            >
        </nav>

        <section class="mt-10" aria-labelledby="providers-heading">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 id="providers-heading" class="text-xl font-semibold">
                    Meistrai ir paslaugų teikėjai
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
                <p class="font-medium">
                    Pagal pasirinktus filtrus teikėjų neradome
                </p>
                <p class="max-w-md text-sm text-muted-foreground">
                    Aprašykite darbą – užklausą pamatys visi tinkami teikėjai,
                    ir jie patys atsiųs pasiūlymus.
                </p>
                <Button as-child>
                    <a :href="createRequestUrl">Sukurti užklausą</a>
                </Button>
            </div>

            <CatalogPagination class="mt-6" :paginated="providers" />
        </section>

        <!-- SEO: vidinės nuorodos į „paslauga mieste" puslapius -->
        <section class="mt-12 border-t pt-8" aria-labelledby="cities-heading">
            <h2 id="cities-heading" class="text-lg font-semibold">
                {{ category.name }} kituose miestuose
            </h2>
            <ul
                class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3"
            >
                <li v-for="popular in popularCities" :key="popular.slug">
                    <Link
                        :href="
                            categoryCity({
                                category: category.slug,
                                city: popular.slug,
                            })
                        "
                        class="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground hover:underline"
                    >
                        <MapPin class="size-3.5" aria-hidden="true" />
                        {{ category.name }} {{ popular.name_locative }}
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
