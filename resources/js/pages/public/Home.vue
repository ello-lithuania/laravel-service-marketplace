<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Brush,
    ClipboardList,
    MapPin,
    MessageSquareText,
    Star,
} from '@lucide/vue';
import CategoryIcon from '@/components/catalog/CategoryIcon.vue';
import ProviderCard from '@/components/catalog/ProviderCard.vue';
import SearchForm from '@/components/catalog/SearchForm.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';
import {
    index as categoriesIndex,
    show as categoryShow,
} from '@/routes/categories';
import { index as providersIndex } from '@/routes/providers';
import type {
    CityOption,
    ProviderCard as ProviderCardType,
    RootCategory,
    SeoMeta,
} from '@/types';

// Pradžios puslapis: duomenys ateina iš HomeController (kategorijos ir miestai – iš cache).
defineProps<{
    categories: RootCategory[];
    popularCities: CityOption[];
    cities: CityOption[];
    featuredProviders: ProviderCardType[];
    seo: SeoMeta;
}>();

// Užklausos kūrimo puslapis atsiras Etape 5, todėl kol kas paprasta nuoroda, ne Wayfinder funkcija
const createRequestUrl = '/uzklausos/nauja';

const steps = [
    {
        icon: ClipboardList,
        title: 'Aprašykite darbą',
        text: 'Nurodykite, ko reikia, kur ir kada. Tai nemokama ir užtrunka porą minučių.',
    },
    {
        icon: MessageSquareText,
        title: 'Gaukite pasiūlymus',
        text: 'Jūsų mieste dirbantys teikėjai atsiųs kainas ir terminus.',
    },
    {
        icon: Star,
        title: 'Išsirinkite ir įvertinkite',
        text: 'Palyginkite atsiliepimus, pasirinkite geriausią ir po darbo palikite įvertinimą.',
    },
];
</script>

<template>
    <SeoHead :seo="seo" />

    <section class="border-b bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-12 md:py-20">
            <h1
                class="max-w-2xl text-3xl font-semibold tracking-tight md:text-5xl"
            >
                Raskite patikimą meistrą per kelias minutes
            </h1>
            <p class="mt-4 max-w-xl text-lg text-muted-foreground">
                Aprašykite darbą, gaukite pasiūlymus iš teikėjų ir išsirinkite
                geriausią – nuo santechniko iki renginių organizatoriaus.
            </p>

            <SearchForm :cities="cities" class="mt-8 max-w-3xl" />

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <Button size="lg" as-child>
                    <a :href="createRequestUrl">Sukurti užklausą</a>
                </Button>
                <Button size="lg" variant="outline" as-child>
                    <a href="#teikejams">Esu paslaugų teikėjas</a>
                </Button>
            </div>
        </div>
    </section>

    <section id="kategorijos" class="mx-auto max-w-6xl px-4 py-16">
        <div class="flex items-end justify-between gap-4">
            <h2 class="text-2xl font-semibold tracking-tight">
                Paslaugų kategorijos
            </h2>
            <Link
                :href="categoriesIndex()"
                class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-primary hover:underline"
            >
                Visos paslaugos
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </div>
        <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
            <Link
                v-for="category in categories"
                :key="category.id"
                :href="categoryShow(category.slug)"
                class="group flex flex-col items-start gap-3 rounded-lg border p-4 transition-colors hover:bg-accent"
            >
                <CategoryIcon
                    :name="category.icon"
                    class="size-6 text-primary"
                />
                <span class="font-medium group-hover:underline">{{
                    category.name
                }}</span>
                <span
                    class="hidden text-sm text-muted-foreground sm:line-clamp-2"
                >
                    {{
                        category.children.map((child) => child.name).join(', ')
                    }}
                </span>
            </Link>
        </div>
    </section>

    <section class="border-y bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-12">
            <h2 class="text-2xl font-semibold tracking-tight">
                Populiarūs miestai
            </h2>
            <ul class="mt-6 flex flex-wrap gap-2">
                <li v-for="city in popularCities" :key="city.slug">
                    <Link
                        :href="
                            providersIndex({ query: { miestas: city.slug } })
                        "
                        class="inline-flex items-center gap-1.5 rounded-full border bg-background px-4 py-2 text-sm transition-colors hover:bg-accent"
                    >
                        <MapPin
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        {{ city.name }}
                    </Link>
                </li>
            </ul>
        </div>
    </section>

    <section
        v-if="featuredProviders.length"
        class="mx-auto max-w-6xl px-4 py-16"
    >
        <div class="flex items-end justify-between gap-4">
            <h2 class="text-2xl font-semibold tracking-tight">
                Geriausiai įvertinti teikėjai
            </h2>
            <Link
                :href="providersIndex()"
                class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-primary hover:underline"
            >
                Visi teikėjai
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </div>
        <div class="mt-6 grid gap-3 lg:grid-cols-2">
            <ProviderCard
                v-for="provider in featuredProviders"
                :key="provider.id"
                :provider="provider"
            />
        </div>
    </section>

    <section id="kaip-tai-veikia" class="border-y bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <h2 class="text-2xl font-semibold tracking-tight">
                Kaip tai veikia
            </h2>
            <ol class="mt-8 grid gap-8 md:grid-cols-3">
                <li v-for="(step, index) in steps" :key="step.title">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 items-center justify-center rounded-full bg-primary text-primary-foreground"
                        >
                            <component
                                :is="step.icon"
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="text-sm text-muted-foreground"
                            >{{ index + 1 }} žingsnis</span
                        >
                    </div>
                    <h3 class="mt-4 font-semibold">{{ step.title }}</h3>
                    <p class="mt-1 text-muted-foreground">{{ step.text }}</p>
                </li>
            </ol>
        </div>
    </section>

    <section id="teikejams" class="mx-auto max-w-6xl px-4 py-16">
        <div
            class="flex flex-col items-start justify-between gap-6 rounded-xl border p-8 md:flex-row md:items-center"
        >
            <div class="flex items-start gap-4">
                <Brush
                    class="mt-1 size-8 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div>
                    <h2 class="text-xl font-semibold">
                        Teikiate paslaugas? Gaukite naujų klientų
                    </h2>
                    <p class="mt-1 text-muted-foreground">
                        Sukurkite profilį, pasirinkite kategorijas ir miestus –
                        naujas užklausas gausite el. paštu.
                    </p>
                </div>
            </div>
            <Button size="lg" as-child>
                <Link :href="register({ query: { role: 'provider' } })"
                    >Tapti teikėju</Link
                >
            </Button>
        </div>
    </section>
</template>
