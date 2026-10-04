<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Bell,
    Check,
    CircleCheck,
    ClipboardPen,
    Images,
    MapPin,
    MessagesSquare,
    Plus,
    Send,
    ShieldCheck,
    Star,
    ThumbsUp,
} from '@lucide/vue';
import { computed } from 'vue';
import CategoryIcon from '@/components/catalog/CategoryIcon.vue';
import ProviderCard from '@/components/catalog/ProviderCard.vue';
import SearchForm from '@/components/catalog/SearchForm.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import HeroVisual from '@/components/home/HeroVisual.vue';
import TestimonialCard from '@/components/home/TestimonialCard.vue';
import FaqList from '@/components/site/FaqList.vue';
import PhotoSlot from '@/components/site/PhotoSlot.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import { Button } from '@/components/ui/button';
import { brandTones, toneFor } from '@/lib/brandTones';
import { formatNumber, formatRating, pluralWord } from '@/lib/format';
import { pricing, register } from '@/routes';
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
    SitePhotoData,
    SiteStats,
    Testimonial,
} from '@/types';

// Pradžios puslapis: duomenys ateina iš HomeController (kategorijos, miestai, nuotraukos, skaičiai – iš cache).
const props = defineProps<{
    categories: RootCategory[];
    popularCities: CityOption[];
    cities: CityOption[];
    featuredProviders: ProviderCardType[];
    seo: SeoMeta;
    photos: {
        hero: SitePhotoData;
        providers: SitePhotoData;
        request: SitePhotoData;
    };
    stats: SiteStats;
    testimonials: Testimonial[];
}>();

// Užklausos kūrimo puslapis – paprasta nuoroda, ne Wayfinder funkcija
const createRequestUrl = '/uzklausos/nauja';

// „Populiaru" po paieška – pirmosios kelių sričių paslaugų grupės (tikri slug'ai iš katalogo)
const popularServices = computed(() =>
    props.categories
        .map((category) => category.children[0])
        .filter((child) => child !== undefined)
        .slice(0, 5),
);

// Skaičius rodom tik kai jie jau įspūdingi – naujoje platformoje „3 teikėjai" atrodytų prastai
const showStats = computed(
    () => props.stats.providers >= 10 && props.stats.reviews >= 10,
);

const statItems = computed(() => [
    {
        value: formatNumber(props.stats.providers),
        label: `${pluralWord(props.stats.providers, ['aktyvus teikėjas', 'aktyvūs teikėjai', 'aktyvių teikėjų'])}`,
    },
    {
        value: formatNumber(props.stats.reviews),
        label: pluralWord(props.stats.reviews, [
            'klientų atsiliepimas',
            'klientų atsiliepimai',
            'klientų atsiliepimų',
        ]),
    },
    {
        value:
            props.stats.rating_avg === null
                ? '–'
                : `${formatRating(props.stats.rating_avg)} / 5`,
        label: 'vidutinis įvertinimas',
    },
    {
        value: formatNumber(props.stats.completed_jobs),
        label: pluralWord(props.stats.completed_jobs, [
            'atliktas darbas',
            'atlikti darbai',
            'atliktų darbų',
        ]),
    },
    {
        value: formatNumber(props.cities.length),
        label: pluralWord(props.cities.length, [
            'savivaldybė',
            'savivaldybės',
            'savivaldybių',
        ]),
    },
]);

const steps = [
    {
        icon: ClipboardPen,
        title: 'Aprašykite, ko reikia',
        text: 'Keli sakiniai apie darbą, vieta ir pageidaujamas laikas. Pridėkite nuotraukų – meistrams bus lengviau įvertinti darbą.',
    },
    {
        icon: MessagesSquare,
        title: 'Gaukite pasiūlymus',
        text: 'Užklausą pamato jūsų mieste dirbantys tos srities teikėjai ir atsiunčia kainą bei terminus. Klausimus aptarsite žinutėmis.',
    },
    {
        icon: ThumbsUp,
        title: 'Išsirinkite ramiai',
        text: 'Palyginkite kainas, atsiliepimus ir atliktų darbų nuotraukas. Po darbo įvertinkite teikėją – padėsite kitiems.',
    },
];

const providerBenefits = [
    'Užklausos pagal jūsų paslaugas ir aptarnaujamus miestus',
    'Profilis su atliktų darbų nuotraukomis ir atsiliepimais',
    'Be privalomo mėnesio mokesčio – kreditų perkate, kai reikia',
];

const faq = [
    {
        q: 'Ar platforma klientams nemokama?',
        a: 'Taip. Užklausos sukūrimas, pasiūlymai ir susirašinėjimas su teikėjais jums nekainuoja nieko. Už darbą atsiskaitote tiesiogiai su pasirinktu teikėju.',
    },
    {
        q: 'Kada gausiu pasiūlymų?',
        a: 'Užklausą iš karto pamato jūsų mieste dirbantys tos srities teikėjai, todėl pirmieji pasiūlymai dažnai ateina dar tą pačią dieną. Apie kiekvieną naują pasiūlymą pranešime el. paštu.',
    },
    {
        q: 'Kaip suprasti, ar teikėju galima pasitikėti?',
        a: 'Kiekviename profilyje matysite klientų atsiliepimus, atliktų darbų nuotraukas ir patirtį. Ženklelis „Patikrintas“ reiškia, kad administratorius patikrino teikėjo duomenis.',
    },
    {
        q: 'Ar privalau pasirinkti kurį nors pasiūlymą?',
        a: 'Ne. Jei netinka nė vienas pasiūlymas, užklausą galite atšaukti – tai nieko nekainuoja.',
    },
    {
        q: 'Teikiu paslaugas – kaip gauti užsakymų?',
        a: 'Užsiregistruokite kaip teikėjas, užpildykite profilį ir pasirinkite paslaugas bei miestus. Apie naujas užklausas pranešime el. paštu, o pasiūlymą išsiunčiate už kreditus.',
    },
];
</script>

<template>
    <SeoHead :seo="seo" />

    <!-- ===== Viršus: antraštė, paieška ir nuotrauka ===== -->
    <section class="relative overflow-hidden" aria-labelledby="hero-title">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-[85%] bg-gradient-to-b from-secondary/70 to-transparent dark:from-secondary/30"
            aria-hidden="true"
        />
        <div
            class="relative page-container grid items-center gap-12 pt-10 pb-14 md:pt-14 lg:grid-cols-[1.08fr_0.92fr] lg:gap-14 lg:pt-16 lg:pb-20 xl:gap-20"
        >
            <div>
                <p
                    class="inline-flex items-center gap-2 rounded-full border border-primary/15 bg-card/80 py-1 pr-3 pl-1.5 text-sm shadow-soft"
                >
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground"
                    >
                        <ShieldCheck class="size-3.5" aria-hidden="true" />
                        Nemokamai
                    </span>
                    <span class="text-muted-foreground"
                        >Klientams – be jokių mokesčių</span
                    >
                </p>

                <h1
                    id="hero-title"
                    class="mt-6 text-[2.5rem] leading-[1.04] font-bold text-balance sm:text-5xl lg:text-[3.6rem] xl:text-[4rem]"
                >
                    Aprašykite darbą&nbsp;–
                    <span class="relative whitespace-nowrap text-primary">
                        meistrai patys
                        <svg
                            class="absolute -bottom-2 left-0 h-3 w-full text-cta"
                            viewBox="0 0 300 12"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M2 9c60-6 140-8 296-4"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="4"
                                stroke-linecap="round"
                            />
                        </svg>
                    </span>
                    pasiūlys kainą
                </h1>
                <p
                    class="mt-6 max-w-xl text-lg leading-relaxed text-pretty text-muted-foreground"
                >
                    Santechnikai, elektrikai, valytojai, kraustytojai ir dar
                    šimtai specialistų visoje Lietuvoje. Palyginkite pasiūlymus
                    bei atsiliepimus ir išsirinkite tinkamiausią.
                </p>

                <SearchForm
                    :cities="cities"
                    size="hero"
                    class="mt-8 max-w-2xl"
                />

                <div
                    v-if="popularServices.length"
                    class="mt-4 flex flex-wrap items-center gap-2 text-sm"
                >
                    <span class="text-muted-foreground">Populiaru:</span>
                    <Link
                        v-for="service in popularServices"
                        :key="service.id"
                        :href="categoryShow(service.slug)"
                        class="rounded-full border bg-card/70 px-3 py-1 text-foreground/80 transition-colors hover:border-primary/40 hover:text-foreground"
                        >{{ service.name }}</Link
                    >
                </div>

                <div
                    class="mt-8 flex flex-col gap-4 border-t pt-8 sm:flex-row sm:items-center"
                >
                    <Button variant="cta" size="xl" as-child>
                        <a :href="createRequestUrl">
                            <Plus class="size-5" aria-hidden="true" />
                            Sukurti užklausą
                        </a>
                    </Button>
                    <ul
                        class="flex flex-col gap-1 text-sm text-muted-foreground"
                    >
                        <li class="flex items-center gap-2">
                            <Check
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            Užtruks porą minučių
                        </li>
                        <li class="flex items-center gap-2">
                            <Check
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            Jokių įsipareigojimų
                        </li>
                    </ul>
                </div>
            </div>

            <HeroVisual
                :photo="photos.hero"
                :providers="featuredProviders"
                :stats="stats"
                class="lg:ml-4"
            />
        </div>
    </section>

    <!-- ===== Skaičiai iš DB (cache 1 val.) ===== -->
    <section class="page-container" aria-label="Platforma skaičiais">
        <dl
            v-if="showStats"
            class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border bg-border shadow-soft sm:grid-cols-3 lg:grid-cols-5"
        >
            <div
                v-for="(item, index) in statItems"
                :key="item.label"
                class="flex flex-col bg-card px-5 py-5 lg:px-6 lg:py-6"
                :class="
                    index === statItems.length - 1
                        ? 'col-span-2 sm:col-span-1'
                        : ''
                "
            >
                <dt class="order-2 mt-1 text-sm text-muted-foreground">
                    {{ item.label }}
                </dt>
                <dd
                    class="order-1 font-display text-3xl font-semibold tracking-tight numeric lg:text-[2.1rem]"
                >
                    {{ item.value }}
                </dd>
            </div>
        </dl>
        <ul
            v-else
            class="grid gap-px overflow-hidden rounded-2xl border bg-border shadow-soft sm:grid-cols-3"
        >
            <li
                v-for="point in [
                    { icon: ShieldCheck, text: 'Klientams – nemokamai' },
                    {
                        icon: BadgeCheck,
                        text: 'Teikėjų duomenis tikrina administracija',
                    },
                    { icon: Star, text: 'Atsiliepimus rašo tik klientai' },
                ]"
                :key="point.text"
                class="flex items-center gap-3 bg-card px-5 py-5 font-medium"
            >
                <component
                    :is="point.icon"
                    class="size-5 text-primary"
                    aria-hidden="true"
                />
                {{ point.text }}
            </li>
        </ul>
    </section>

    <!-- ===== Kategorijos (su nuotraukomis arba spalviniu atsarginiu dizainu) ===== -->
    <section
        id="kategorijos"
        class="page-container scroll-mt-24 py-20 lg:py-24"
        aria-labelledby="categories-title"
    >
        <SectionHeading
            id="categories-title"
            eyebrow="Paslaugos"
            title="Ko šiandien reikia?"
            description="Nuo užsikimšusios kriauklės iki buto remonto ar šventės – pasirinkite sritį ir pamatysite ją teikiančius meistrus."
        >
            <template #action>
                <Button variant="outline" as-child>
                    <Link :href="categoriesIndex()">
                        Visos paslaugos
                        <ArrowRight aria-hidden="true" />
                    </Link>
                </Button>
            </template>
        </SectionHeading>

        <ul
            class="mt-10 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4"
        >
            <li
                v-for="(category, index) in categories"
                :key="category.id"
                :class="index === 0 ? 'col-span-2 lg:row-span-2' : ''"
            >
                <Link
                    :href="categoryShow(category.slug)"
                    class="group relative isolate flex h-full flex-col justify-end overflow-hidden rounded-2xl bg-muted p-4 text-white shadow-soft transition-shadow duration-300 hover:shadow-lift focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:p-5"
                    :class="
                        index === 0
                            ? 'aspect-[4/3] sm:aspect-[2/1] lg:aspect-auto lg:min-h-full'
                            : 'aspect-[4/3]'
                    "
                >
                    <PhotoSlot
                        :src="category.image_url"
                        :alt="category.name"
                        :tone="toneFor(category.icon, category.slug)"
                        :icon="category.icon"
                        :sizes="
                            index === 0
                                ? '(min-width: 1024px) 50vw, 100vw'
                                : '(min-width: 1024px) 25vw, 50vw'
                        "
                    >
                    </PhotoSlot>
                    <!-- Tamsus perėjimas apačioje – baltas tekstas įskaitomas ant bet kokios nuotraukos -->
                    <div
                        class="absolute inset-0 bg-gradient-to-t to-transparent"
                        :class="
                            category.image_url
                                ? 'from-black/75 via-black/25'
                                : 'from-black/40 via-black/5'
                        "
                        aria-hidden="true"
                    />
                    <div class="relative">
                        <span
                            class="mb-3 flex size-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur-sm"
                            :class="index === 0 ? 'sm:size-11' : ''"
                        >
                            <CategoryIcon
                                :name="category.icon"
                                :class="
                                    index === 0 ? 'size-5 sm:size-6' : 'size-5'
                                "
                            />
                        </span>
                        <span
                            class="block font-display leading-tight font-semibold tracking-tight"
                            :class="
                                index === 0
                                    ? 'text-xl sm:text-3xl'
                                    : 'text-base sm:text-lg'
                            "
                            >{{ category.name }}</span
                        >
                        <span
                            v-if="category.children.length"
                            class="mt-1 text-sm text-white/80"
                            :class="
                                index === 0
                                    ? 'line-clamp-1'
                                    : 'hidden sm:line-clamp-1'
                            "
                        >
                            {{
                                category.children
                                    .map((child) => child.name)
                                    .join(' · ')
                            }}
                        </span>
                        <span
                            v-if="index === 0"
                            class="mt-4 hidden items-center gap-1.5 rounded-full bg-white px-4 py-2 text-sm font-semibold text-foreground transition-colors group-hover:bg-cta group-hover:text-cta-foreground sm:inline-flex"
                        >
                            Rasti meistrą
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </span>
                    </div>
                </Link>
            </li>
            <!-- Paskutinė kortelė – kvietimas, jei tinkamos srities nėra -->
            <li class="col-span-2 md:col-span-3 lg:col-span-1">
                <a
                    :href="createRequestUrl"
                    class="group flex h-full min-h-40 flex-col justify-between gap-4 rounded-2xl border-2 border-dashed border-primary/25 bg-secondary/50 p-5 transition-colors hover:border-primary/50 hover:bg-secondary"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground"
                    >
                        <Plus class="size-5" aria-hidden="true" />
                    </span>
                    <span>
                        <span
                            class="block font-display text-lg font-semibold tracking-tight"
                            >Neradote savo darbo?</span
                        >
                        <span class="mt-1 block text-sm text-muted-foreground"
                            >Aprašykite jį savais žodžiais – tinkami teikėjai
                            atsilieps.</span
                        >
                    </span>
                </a>
            </li>
        </ul>
    </section>

    <!-- ===== Kaip tai veikia ===== -->
    <section
        id="kaip-tai-veikia"
        class="scroll-mt-20 border-y bg-surface"
        aria-labelledby="how-title"
    >
        <div
            class="page-container grid items-center gap-12 py-20 lg:grid-cols-2 lg:gap-16 lg:py-24"
        >
            <div class="relative order-2 lg:order-1">
                <div
                    class="relative aspect-[4/3] overflow-hidden rounded-3xl shadow-lift"
                >
                    <PhotoSlot
                        :src="photos.request?.url"
                        :alt="
                            photos.request?.alt ??
                            'Klientas aptaria darbą su meistru'
                        "
                        :width="1600"
                        :height="1200"
                        sizes="(min-width: 1024px) 45vw, 100vw"
                    >
                        <template #fallback>
                            <!-- Be nuotraukos: užklausos formos iliustracija -->
                            <div
                                class="absolute inset-0 flex items-center justify-center p-6 sm:p-10"
                                :style="{
                                    backgroundImage: `linear-gradient(135deg, ${brandTones.pine.from}, ${brandTones.teal.to})`,
                                }"
                                aria-hidden="true"
                            >
                                <div
                                    class="absolute inset-0 pattern-dots text-white/[0.08]"
                                />
                                <div
                                    class="relative w-full max-w-sm rounded-2xl bg-card p-5 text-card-foreground shadow-float"
                                >
                                    <p
                                        class="text-xs font-semibold text-muted-foreground"
                                    >
                                        Ko reikia?
                                    </p>
                                    <p
                                        class="mt-2 rounded-lg border bg-background px-3 py-2.5 text-sm"
                                    >
                                        Virtuvėje sumontuoti 6 šviestuvus ir 2
                                        naujus el. lizdus
                                    </p>
                                    <div
                                        class="mt-3 flex flex-wrap gap-1.5 text-xs"
                                    >
                                        <span
                                            class="rounded-full bg-secondary px-2.5 py-1 text-secondary-foreground"
                                            >Elektros instaliacija</span
                                        >
                                        <span
                                            class="rounded-full bg-secondary px-2.5 py-1 text-secondary-foreground"
                                            >Vilnius</span
                                        >
                                        <span
                                            class="rounded-full bg-secondary px-2.5 py-1 text-secondary-foreground"
                                            >Šią savaitę</span
                                        >
                                    </div>
                                    <div
                                        class="mt-4 flex items-center justify-center gap-2 rounded-lg bg-cta py-2.5 text-sm font-semibold text-cta-foreground"
                                    >
                                        <Send class="size-4" />
                                        Siųsti užklausą
                                    </div>
                                </div>
                            </div>
                        </template>
                    </PhotoSlot>
                </div>
                <div
                    class="absolute -bottom-5 left-4 flex items-center gap-3 rounded-2xl border bg-card px-4 py-3 shadow-float sm:left-8"
                >
                    <CircleCheck
                        class="size-6 text-primary"
                        aria-hidden="true"
                    />
                    <span class="text-sm">
                        <span class="block font-semibold"
                            >Užklausa išsiųsta</span
                        >
                        <span class="text-muted-foreground"
                            >ją mato tinkami teikėjai</span
                        >
                    </span>
                </div>
            </div>

            <div class="order-1 lg:order-2">
                <SectionHeading
                    id="how-title"
                    eyebrow="Kaip tai veikia"
                    title="Trys žingsniai iki atlikto darbo"
                />
                <ol class="relative mt-10 space-y-8">
                    <!-- Vertikali linija jungia žingsnius -->
                    <span
                        class="absolute top-2 bottom-2 left-6 w-px bg-border"
                        aria-hidden="true"
                    />
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="relative flex gap-5"
                    >
                        <span
                            class="relative flex size-12 shrink-0 items-center justify-center rounded-2xl border bg-card text-primary shadow-soft"
                        >
                            <component
                                :is="step.icon"
                                class="size-5"
                                aria-hidden="true"
                            />
                            <span
                                class="absolute -top-2 -right-2 flex size-6 items-center justify-center rounded-full bg-cta font-display text-xs font-bold text-cta-foreground"
                                >{{ index + 1 }}</span
                            >
                        </span>
                        <div class="pt-1">
                            <h3
                                class="font-display text-xl font-semibold tracking-tight"
                            >
                                {{ step.title }}
                            </h3>
                            <p
                                class="mt-1.5 leading-relaxed text-muted-foreground"
                            >
                                {{ step.text }}
                            </p>
                        </div>
                    </li>
                </ol>
                <div class="mt-10 flex flex-wrap items-center gap-4">
                    <Button variant="cta" size="xl" as-child>
                        <a :href="createRequestUrl">Sukurti užklausą</a>
                    </Button>
                    <span class="text-sm text-muted-foreground"
                        >Nemokamai, be registracijos mokesčių</span
                    >
                </div>
            </div>
        </div>
    </section>

    <!-- ===== Geriausiai įvertinti teikėjai ===== -->
    <section
        v-if="featuredProviders.length"
        class="page-container py-20 lg:py-24"
        aria-labelledby="featured-title"
    >
        <SectionHeading
            id="featured-title"
            eyebrow="Teikėjai"
            title="Geriausiai įvertinti teikėjai"
            description="Daugiausia gerų atsiliepimų surinkę meistrai ir įmonės. Profilyje rasite kainas, darbų nuotraukas ir visus atsiliepimus."
        >
            <template #action>
                <Button variant="outline" as-child>
                    <Link :href="providersIndex()">
                        Visi teikėjai
                        <ArrowRight aria-hidden="true" />
                    </Link>
                </Button>
            </template>
        </SectionHeading>
        <ul class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <li v-for="provider in featuredProviders" :key="provider.id">
                <ProviderCard :provider="provider" layout="tile" />
            </li>
        </ul>
    </section>

    <!-- ===== Atsiliepimai ===== -->
    <section
        v-if="testimonials.length"
        class="bg-secondary/50 py-20 lg:py-24 dark:bg-secondary/20"
        aria-labelledby="testimonials-title"
    >
        <div class="page-container">
            <SectionHeading
                id="testimonials-title"
                eyebrow="Atsiliepimai"
                title="Ką sako klientai"
                description="Naujausi penkių žvaigždučių įvertinimai – tikri klientų žodžiai po atlikto darbo."
            />
            <div class="mt-10 grid grid-cols-1 gap-4 lg:grid-cols-[1.1fr_1fr]">
                <TestimonialCard :testimonial="testimonials[0]" featured />
                <div class="grid gap-4 sm:grid-cols-2">
                    <TestimonialCard
                        v-for="(testimonial, index) in testimonials.slice(1, 5)"
                        :key="testimonial.id"
                        :testimonial="testimonial"
                        :class="index >= 2 ? 'hidden sm:flex' : ''"
                    />
                </div>
            </div>
        </div>
    </section>

    <!-- ===== Miestai ===== -->
    <section
        class="page-container grid gap-10 py-20 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16 lg:py-24"
        aria-labelledby="cities-title"
    >
        <div>
            <SectionHeading
                id="cities-title"
                eyebrow="Miestai"
                title="Meistrai visoje Lietuvoje"
                :description="`Teikėjai dirba visose ${cities.length} savivaldybėse – nuo didmiesčių iki rajonų. Pasirinkite miestą ir pamatysite, kas dirba arčiausiai.`"
            />
        </div>
        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <li v-for="city in popularCities" :key="city.slug">
                <Link
                    :href="providersIndex({ query: { miestas: city.slug } })"
                    class="group flex items-center justify-between gap-2 rounded-xl border bg-card px-4 py-3.5 shadow-soft transition-colors hover:border-primary/40 hover:bg-accent"
                >
                    <span class="flex min-w-0 items-center gap-2.5">
                        <MapPin
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{
                                city.name
                            }}</span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >meistrai {{ city.name_locative }}</span
                            >
                        </span>
                    </span>
                    <ArrowRight
                        class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                        aria-hidden="true"
                    />
                </Link>
            </li>
        </ul>
    </section>

    <!-- ===== Kvietimas teikėjams ===== -->
    <section
        id="teikejams"
        class="page-container scroll-mt-24"
        aria-labelledby="providers-cta-title"
    >
        <div
            class="relative isolate overflow-hidden rounded-[2rem] bg-brand-deep text-brand-deep-foreground"
        >
            <div
                class="pointer-events-none absolute inset-0 -z-10 pattern-dots text-white/[0.05]"
                aria-hidden="true"
            />
            <div
                class="grid items-center gap-10 p-6 sm:p-10 lg:grid-cols-2 lg:gap-12 lg:p-14"
            >
                <div>
                    <SectionHeading
                        id="providers-cta-title"
                        tone="light"
                        eyebrow="Teikėjams"
                        title="Daugiau užsakymų&nbsp;– be išlaidų reklamai"
                        description="Gaukite užklausas iš klientų, kuriems paslaugos reikia dabar. Mokate tik už išsiųstus pasiūlymus, o jei klientas pasiūlymo neatidaro – kreditai grįžta."
                    />
                    <ul class="mt-8 space-y-3">
                        <li
                            v-for="benefit in providerBenefits"
                            :key="benefit"
                            class="flex items-start gap-3"
                        >
                            <span
                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-cta text-cta-foreground"
                            >
                                <Check class="size-3.5" aria-hidden="true" />
                            </span>
                            {{ benefit }}
                        </li>
                    </ul>
                    <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                        <Button variant="cta" size="xl" as-child>
                            <Link
                                :href="
                                    register({ query: { role: 'provider' } })
                                "
                                >Tapti teikėju</Link
                            >
                        </Button>
                        <Button variant="on-dark" size="xl" as-child>
                            <Link :href="pricing()">Kainos ir kreditai</Link>
                        </Button>
                    </div>
                </div>

                <div class="relative">
                    <div
                        class="relative aspect-[4/3] overflow-hidden rounded-3xl ring-1 ring-white/10"
                    >
                        <PhotoSlot
                            :src="photos.providers?.url"
                            :alt="
                                photos.providers?.alt ??
                                'Paslaugų teikėjas savo darbo vietoje'
                            "
                            :width="1600"
                            :height="1200"
                            sizes="(min-width: 1024px) 45vw, 100vw"
                        >
                            <template #fallback>
                                <!-- Be nuotraukos: į teikėjo paskyrą ateinančios užklausos -->
                                <div
                                    class="absolute inset-0 flex flex-col justify-center gap-3 bg-white/[0.04] p-5 sm:p-8"
                                    aria-hidden="true"
                                >
                                    <div
                                        v-for="(request, index) in [
                                            {
                                                title: 'Laminato klojimas, 45 m²',
                                                meta: 'Vilnius · iki 700 €',
                                            },
                                            {
                                                title: 'Dviejų kambarių buto dažymas',
                                                meta: 'Kaunas · per 2 savaites',
                                            },
                                            {
                                                title: 'Virtuvės baldų surinkimas',
                                                meta: 'Klaipėda · šį savaitgalį',
                                            },
                                        ]"
                                        :key="request.title"
                                        class="flex items-center gap-3 rounded-2xl bg-card p-4 text-card-foreground shadow-lift"
                                        :class="
                                            index === 1
                                                ? 'ml-6 sm:ml-10'
                                                : index === 2
                                                  ? 'mr-6 sm:mr-10'
                                                  : ''
                                        "
                                    >
                                        <span
                                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary"
                                        >
                                            <Bell
                                                v-if="index === 0"
                                                class="size-5"
                                            />
                                            <Images v-else class="size-5" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span
                                                class="block truncate text-sm font-semibold"
                                                >{{ request.title }}</span
                                            >
                                            <span
                                                class="block truncate text-xs text-muted-foreground"
                                                >{{ request.meta }}</span
                                            >
                                        </span>
                                        <span
                                            v-if="index === 0"
                                            class="hidden rounded-lg bg-cta px-3 py-1.5 text-xs font-semibold text-cta-foreground sm:inline"
                                            >Siųsti pasiūlymą</span
                                        >
                                    </div>
                                </div>
                            </template>
                        </PhotoSlot>
                    </div>
                    <div
                        v-if="photos.providers"
                        class="absolute -bottom-4 left-4 flex items-center gap-3 rounded-2xl bg-card p-3.5 pr-5 text-card-foreground shadow-float sm:left-8"
                    >
                        <span
                            class="flex size-10 items-center justify-center rounded-xl bg-cta/15 text-[#8a4b0c] dark:text-cta"
                        >
                            <Bell class="size-5" aria-hidden="true" />
                        </span>
                        <span class="text-sm">
                            <span class="block font-semibold"
                                >Nauja užklausa jūsų mieste</span
                            >
                            <span class="text-muted-foreground"
                                >Laminato klojimas, 45 m²</span
                            >
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== Dažni klausimai ===== -->
    <section
        id="duk"
        class="page-container grid scroll-mt-24 gap-10 py-20 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16 lg:py-24"
        aria-labelledby="faq-title"
    >
        <div>
            <SectionHeading
                id="faq-title"
                eyebrow="DUK"
                title="Dažni klausimai"
                description="Trumpai apie tai, kaip veikia užklausos, pasiūlymai ir atsiliepimai."
            />
            <p class="mt-6 text-sm text-muted-foreground">
                Teikiate paslaugas?
                <Link
                    :href="pricing()"
                    class="font-medium text-primary underline-offset-4 hover:underline"
                    >Kainos ir kreditai teikėjams</Link
                >
            </p>
        </div>
        <FaqList :items="faq" />
    </section>
</template>
