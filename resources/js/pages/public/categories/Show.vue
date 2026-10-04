<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CategoryBanner from '@/components/catalog/CategoryBanner.vue';
import CategoryIcon from '@/components/catalog/CategoryIcon.vue';
import ProviderResults from '@/components/catalog/ProviderResults.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import PhotoSlot from '@/components/site/PhotoSlot.vue';
import { filtersToQuery, visitWithFilters } from '@/lib/catalog';
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
        /** Etapas 10: sava arba srities nuotrauka (null – atsarginis dizainas) */
        image_url: string | null;
        image_wide_url: string | null;
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

const description = computed(
    () =>
        props.category.description ??
        'Palyginkite meistrų atsiliepimus ir kainas arba aprašykite darbą – tinkami teikėjai patys atsiųs pasiūlymus.',
);

// Užrašas virš antraštės: tėvinė sritis (2–3 lygiai) arba „Paslaugos" (1 lygis)
const eyebrow = computed(
    () => props.breadcrumbs[props.breadcrumbs.length - 1]?.name ?? 'Paslaugos',
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

    <div class="page-container pt-6 pb-20 md:pt-8">
        <Breadcrumbs :breadcrumbs="breadcrumbItems" />

        <CategoryBanner
            class="mt-4"
            :title="heading"
            :description="description"
            :eyebrow="eyebrow"
            :icon="category.icon"
            :slug="category.slug"
            :image-url="category.image_wide_url"
            :providers-total="providers.meta.total"
            :create-request-url="createRequestUrl"
            :compact="category.depth > 1"
        />

        <!-- 1 lygis: 2 lygio grupės su 3 lygio paslaugomis -->
        <section
            v-if="category.depth === 1 && subcategories.length"
            class="mt-14"
            aria-labelledby="groups-title"
        >
            <h2 id="groups-title" class="text-2xl font-semibold">
                Paslaugos šioje srityje
            </h2>
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="group in subcategories"
                    :key="group.id"
                    class="group/card flex flex-col overflow-hidden rounded-2xl border bg-card shadow-soft transition-shadow hover:shadow-lift"
                >
                    <!-- Grupės nuotrauka – tik jei administratorius ją įkėlė -->
                    <div
                        v-if="group.image_url"
                        class="group relative aspect-[16/7] overflow-hidden"
                    >
                        <PhotoSlot :src="group.image_url" :alt="group.name" />
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <h3 class="flex items-center gap-3">
                            <span
                                v-if="!group.image_url"
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary"
                            >
                                <CategoryIcon
                                    :name="category.icon"
                                    class="size-4.5"
                                />
                            </span>
                            <Link
                                :href="categoryUrl(group.slug)"
                                class="font-display text-lg leading-tight font-semibold tracking-tight hover:text-primary"
                            >
                                {{ group.name }}
                            </Link>
                        </h3>
                        <ul class="mt-4 flex flex-wrap gap-1.5">
                            <li v-for="leaf in group.children" :key="leaf.id">
                                <Link
                                    :href="categoryUrl(leaf.slug)"
                                    class="inline-flex rounded-full bg-muted px-3 py-1 text-sm text-foreground/80 transition-colors hover:bg-secondary hover:text-secondary-foreground"
                                    >{{ leaf.name }}</Link
                                >
                            </li>
                        </ul>
                        <Link
                            :href="categoryUrl(group.slug)"
                            class="mt-auto inline-flex items-center gap-1 pt-5 text-sm font-medium text-primary"
                        >
                            Visi meistrai
                            <ArrowRight
                                class="size-4 transition-transform group-hover/card:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </Link>
                    </div>
                </li>
            </ul>
        </section>

        <!-- 2 lygis: jo paslaugos; 3 lygis: kitos tos pačios grupės paslaugos -->
        <nav
            v-else-if="subcategories.length"
            class="mt-6"
            aria-label="Susijusios paslaugos"
        >
            <ul class="flex flex-wrap gap-2">
                <li v-for="item in subcategories" :key="item.id">
                    <Link
                        :href="categoryUrl(item.slug)"
                        :aria-current="
                            item.id === category.id ? 'page' : undefined
                        "
                        class="inline-flex rounded-full border bg-card px-3.5 py-1.5 text-sm shadow-xs transition-colors hover:border-primary/40 aria-[current=page]:border-primary aria-[current=page]:bg-primary aria-[current=page]:text-primary-foreground"
                        >{{ item.name }}</Link
                    >
                </li>
            </ul>
        </nav>

        <ProviderResults
            class="mt-14"
            title="Meistrai ir paslaugų teikėjai"
            :providers="providers"
            :filters="filters"
            :cities="cities"
            :sort-options="sortOptions"
            :create-request-url="createRequestUrl"
            @change="applyFilters"
        />

        <!-- SEO: vidinės nuorodos į „paslauga mieste" puslapius -->
        <section
            class="mt-20 rounded-3xl border bg-surface p-6 sm:p-8"
            aria-labelledby="cities-heading"
        >
            <h2 id="cities-heading" class="text-xl font-semibold">
                {{ category.name }} kituose miestuose
            </h2>
            <ul class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <li v-for="popular in popularCities" :key="popular.slug">
                    <Link
                        :href="
                            categoryCity({
                                category: category.slug,
                                city: popular.slug,
                            })
                        "
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    >
                        <MapPin
                            class="size-3.5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span class="truncate"
                            >{{ category.name }}
                            {{ popular.name_locative }}</span
                        >
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
