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
import ProviderCard from '@/components/catalog/ProviderCard.vue';
import SearchForm from '@/components/catalog/SearchForm.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import HeroVisual from '@/components/home/HeroVisual.vue';
import TestimonialCard from '@/components/home/TestimonialCard.vue';
import FaqList from '@/components/site/FaqList.vue';
import PhotoSlot from '@/components/site/PhotoSlot.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import { Button } from '@/components/ui/button';
import { toneFor } from '@/lib/brandTones';
import { formatNumber, formatRating, plural, pluralWord } from '@/lib/format';
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
    /** Etapas 11: providers_count – aktyvių teikėjų skaičius srityje (SiteHighlights, cache 1 val.) */
    categories: (RootCategory & { providers_count: number })[];
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

// Etapas 11: keturi skaičiai vienoje eilėje (variantas A)
const statItems = computed(() => [
    {
        value: formatNumber(props.stats.providers),
        label: pluralWord(props.stats.providers, [
            'aktyvus meistras',
            'aktyvūs meistrai',
            'aktyvių meistrų',
        ]),
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
        value: formatNumber(props.cities.length),
        label: `${pluralWord(props.cities.length, ['savivaldybė', 'savivaldybės', 'savivaldybių'])} visoje Lietuvoje`,
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

    <!-- ===== Viršus (variantas A): mėlynas fonas tęsia antraštę, paieška ir nuotrauka ===== -->
    <section
        class="relative bg-brand text-brand-foreground"
        aria-labelledby="hero-title"
    >
        <div
            class="page-container grid items-center gap-12 pt-8 pb-28 sm:pt-12 lg:grid-cols-12 lg:gap-8 lg:pt-14 lg:pb-36"
        >
            <div class="lg:col-span-7">
                <p
                    class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold"
                >
                    <ShieldCheck class="size-4 text-cta" aria-hidden="true" />
                    Klientams – visiškai nemokamai
                </p>

                <h1
                    id="hero-title"
                    class="mt-6 text-[2.75rem] leading-[0.98] font-black tracking-[-0.035em] text-balance text-white sm:text-6xl lg:text-[4.25rem] xl:text-[4.75rem]"
                >
                    Meistras jūsų darbui&nbsp;–
                    <span class="block text-cta">per kelias minutes</span>
                </h1>
                <p
                    class="mt-6 max-w-xl text-lg leading-relaxed text-pretty text-brand-muted sm:text-xl"
                >
                    Aprašykite, ką reikia padaryti. Meistrai iš jūsų miesto
                    patys atsiųs pasiūlymus su kainomis – jums lieka tik
                    išsirinkti.
                </p>

                <SearchForm
                    :cities="cities"
                    size="hero"
                    class="mt-8 max-w-3xl"
                />

                <div
                    v-if="popularServices.length"
                    class="mt-5 flex flex-wrap items-center gap-2 text-[0.9375rem]"
                >
                    <span class="text-brand-muted">Dažniausiai ieško:</span>
                    <Link
                        v-for="service in popularServices"
                        :key="service.id"
                        :href="categoryShow(service.slug)"
                        class="rounded-full bg-white/12 px-3.5 py-1.5 text-white transition-colors hover:bg-white/20 focus-visible:ring-[3px] focus-visible:ring-white/50 focus-visible:outline-none"
                        >{{ service.name }}</Link
                    >
                </div>

                <!-- Telefone antraštės mygtukas paslėptas (per siaura), todėl pagrindinis veiksmas – čia -->
                <Button
                    variant="cta"
                    size="xl"
                    class="mt-6 w-full rounded-xl sm:hidden"
                    as-child
                >
                    <a :href="createRequestUrl">
                        <Plus class="size-5" aria-hidden="true" />
                        Sukurti užklausą
                    </a>
                </Button>
            </div>

            <HeroVisual
                :photo="photos.hero"
                :providers="featuredProviders"
                class="lg:col-span-5"
            />
        </div>
    </section>

    <!-- ===== Skaičiai iš DB (cache 1 val.): balta kortelė užlenda ant mėlyno viršaus ===== -->
    <section
        class="relative z-10 page-container -mt-16 lg:-mt-20"
        aria-label="Platforma skaičiais"
    >
        <dl
            v-if="showStats"
            class="grid grid-cols-2 gap-y-6 rounded-3xl bg-card py-6 shadow-float lg:grid-cols-4 lg:py-8"
        >
            <div
                v-for="(item, index) in statItems"
                :key="item.label"
                class="flex flex-col px-5 sm:px-8"
                :class="
                    // Telefone 2 stulpeliai (linija po 1 ir 3), kompiuteryje 4 (linija po visų, išskyrus paskutinį)
                    index % 2 === 0
                        ? 'border-r'
                        : index < statItems.length - 1
                          ? 'lg:border-r'
                          : ''
                "
            >
                <dt
                    class="order-2 mt-1 text-sm text-muted-foreground sm:text-base"
                >
                    {{ item.label }}
                </dt>
                <dd
                    class="order-1 text-3xl font-black tracking-[-0.02em] text-primary numeric sm:text-4xl lg:text-[2.75rem]"
                >
                    {{ item.value }}
                </dd>
            </div>
        </dl>
        <ul
            v-else
            class="grid gap-px overflow-hidden rounded-3xl bg-border shadow-float sm:grid-cols-3"
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
                class="flex items-center gap-3 bg-card px-6 py-6 font-semibold"
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

    <!-- ===== Kategorijos: nuotrauka viršuje, pavadinimas ir teikėjų skaičius apačioje ===== -->
    <section
        id="kategorijos"
        class="page-container scroll-mt-24 py-20 lg:py-24"
        aria-labelledby="categories-title"
    >
        <SectionHeading
            id="categories-title"
            eyebrow="Paslaugos"
            title="Ką šiandien sutvarkysime?"
        >
            <template #action>
                <Link
                    :href="categoriesIndex()"
                    class="group inline-flex items-center gap-2 text-[1.0625rem] font-bold text-primary underline-offset-4 hover:underline"
                >
                    Visos paslaugos
                    <ArrowRight
                        class="size-[1.125rem] transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
            </template>
        </SectionHeading>

        <ul
            class="mt-8 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:mt-10 lg:grid-cols-4 lg:gap-6"
        >
            <li v-for="category in categories" :key="category.id">
                <Link
                    :href="categoryShow(category.slug)"
                    class="group flex h-full flex-col overflow-hidden rounded-[1.375rem] border bg-card transition-[box-shadow,transform,border-color] duration-300 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lift focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <div
                        class="relative aspect-[16/10] overflow-hidden bg-muted"
                    >
                        <!-- alt="" – pavadinimas parašytas po nuotrauka, ekrano skaitytuvui jo nekartojam -->
                        <PhotoSlot
                            :src="category.image_url"
                            alt=""
                            :tone="toneFor(category.icon, category.slug)"
                            :icon="category.icon"
                            sizes="(min-width: 1024px) 25vw, (min-width: 768px) 33vw, 50vw"
                        />
                    </div>
                    <div
                        class="flex flex-1 flex-col gap-1 p-3.5 sm:px-5 sm:py-4"
                    >
                        <span
                            class="text-base leading-tight font-extrabold tracking-[-0.01em] sm:text-lg lg:text-[1.1875rem]"
                            >{{ category.name }}</span
                        >
                        <span
                            class="line-clamp-1 text-sm text-muted-foreground sm:text-[0.9375rem]"
                        >
                            <template v-if="category.providers_count > 0">{{
                                plural(category.providers_count, [
                                    'meistras',
                                    'meistrai',
                                    'meistrų',
                                ])
                            }}</template>
                            <template v-else>{{
                                category.children
                                    .map((child) => child.name)
                                    .join(' · ')
                            }}</template>
                        </span>
                    </div>
                </Link>
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
                                class="absolute inset-0 flex items-center justify-center bg-brand p-6 sm:p-10"
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
