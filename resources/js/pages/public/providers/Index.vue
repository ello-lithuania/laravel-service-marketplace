<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BadgeCheck, MessageSquareText, Star } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import ProviderResults from '@/components/catalog/ProviderResults.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import { filtersToQuery, visitWithFilters } from '@/lib/catalog';
import { home } from '@/routes';
import { index as categoriesIndex } from '@/routes/categories';
import { index } from '@/routes/providers';
import type {
    CatalogFilters,
    CityOption,
    Paginated,
    ProviderCard as ProviderCardType,
    SeoMeta,
    SortOption,
} from '@/types';

// Visi aktyvūs teikėjai su filtrais: /meistrai?miestas=kaunas&patikrinti=1
const props = defineProps<{
    providers: Paginated<ProviderCardType>;
    filters: CatalogFilters;
    sortOptions: SortOption[];
    cities: CityOption[];
    seo: SeoMeta;
}>();

const selectedCity = computed(() =>
    props.cities.find((city) => city.slug === props.filters.miestas),
);

function applyFilters(next: CatalogFilters): void {
    visitWithFilters(
        index.url({
            query: { ...filtersToQuery(next), miestas: next.miestas },
        }),
    );
}
</script>

<template>
    <SeoHead :seo="seo" />

    <section
        class="border-b bg-gradient-to-b from-secondary/70 to-background dark:from-secondary/30"
    >
        <div class="page-container pt-6 pb-10 md:pt-8 md:pb-12">
            <Breadcrumbs
                :breadcrumbs="[
                    { title: 'Pradžia', href: home() },
                    { title: 'Meistrai', href: index() },
                ]"
            />
            <div
                class="mt-6 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="max-w-2xl">
                    <h1
                        class="text-4xl leading-[1.08] font-bold text-balance md:text-5xl"
                    >
                        {{
                            selectedCity
                                ? `Meistrai ir paslaugų teikėjai ${selectedCity.name_locative}`
                                : 'Meistrai ir paslaugų teikėjai'
                        }}
                    </h1>
                    <p class="mt-4 text-lg text-muted-foreground">
                        Ieškote konkrečios paslaugos?
                        <Link
                            :href="categoriesIndex()"
                            class="inline-flex items-center gap-1 font-medium text-primary underline-offset-4 hover:underline"
                            >Išsirinkite kategoriją
                            <ArrowRight class="size-4" aria-hidden="true"
                        /></Link>
                        – pamatysite tik ją teikiančius meistrus ir jų kainas.
                    </p>
                </div>
                <ul
                    class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground"
                >
                    <li class="flex items-center gap-2">
                        <Star
                            class="size-4 fill-star text-star"
                            aria-hidden="true"
                        />
                        Tikri klientų atsiliepimai
                    </li>
                    <li class="flex items-center gap-2">
                        <BadgeCheck
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        Patikrinti teikėjai
                    </li>
                    <li class="flex items-center gap-2">
                        <MessageSquareText
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        Pasiūlymai nemokamai
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <div class="page-container pt-10 pb-20">
        <ProviderResults
            title="Visi teikėjai"
            :providers="providers"
            :filters="filters"
            :cities="cities"
            :sort-options="sortOptions"
            empty-text="Pabandykite pasirinkti kitą miestą arba išjungti dalį filtrų. Arba aprašykite darbą – tinkami teikėjai patys atsiųs pasiūlymus."
            @change="applyFilters"
        />
    </div>
</template>
