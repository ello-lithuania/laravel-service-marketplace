<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    CalendarDays,
    ExternalLink,
    MapPin,
    MessageSquareReply,
    Plus,
    ShieldCheck,
    Sparkle,
    Star,
    UserPlus,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import ReportDialog from '@/components/complaints/ReportDialog.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import PortfolioGallery from '@/components/catalog/PortfolioGallery.vue';
import ProBadge from '@/components/catalog/ProBadge.vue';
import ProviderAvatar from '@/components/catalog/ProviderAvatar.vue';
import RatingStars from '@/components/catalog/RatingStars.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import VerifiedBadge from '@/components/catalog/VerifiedBadge.vue';
import PhotoFallback from '@/components/site/PhotoFallback.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { toneFor } from '@/lib/brandTones';
import {
    formatDate,
    formatPriceFrom,
    formatRating,
    plural,
} from '@/lib/format';
import { home } from '@/routes';
import { show as categoryShow } from '@/routes/categories';
import { index as providersIndex, show } from '@/routes/providers';
import type {
    BreadcrumbItem,
    CategoryLink,
    Paginated,
    PortfolioItem,
    PublicProviderProfile,
    PublicReview,
    RatingBucket,
    SeoMeta,
} from '@/types';

const props = defineProps<{
    provider: PublicProviderProfile;
    portfolio: PortfolioItem[];
    reviews: Paginated<PublicReview>;
    ratingDistribution: RatingBucket[];
    mainCategory: CategoryLink | null;
    seo: SeoMeta;
}>();

const createRequestUrl = '/uzklausos/nauja';

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: 'Pradžia', href: home() },
    props.mainCategory
        ? {
              title: props.mainCategory.name,
              href: categoryShow(props.mainCategory.slug),
          }
        : { title: 'Meistrai', href: providersIndex() },
    { title: props.provider.display_name, href: show(props.provider.slug) },
]);

const ratingTotal = computed(() =>
    props.ratingDistribution.reduce((sum, bucket) => sum + bucket.count, 0),
);

function bucketPercent(count: number): number {
    return ratingTotal.value === 0
        ? 0
        : Math.round((count / ratingTotal.value) * 100);
}

// Viršelio atsarginis dizainas – teikėjo spalvos tonas (toks pat kaip inicialų fono)
const coverTone = computed(() => toneFor(null, props.provider.display_name));

// Skilčių meniu (nuorodos į #skiltis) – tik tos, kurios puslapyje yra
const sections = computed(() =>
    [
        {
            id: 'apie',
            label: 'Apie',
            show: Boolean(props.provider.description),
        },
        { id: 'paslaugos', label: 'Paslaugos ir kainos', show: true },
        {
            id: 'darbai',
            label: `Darbai (${props.portfolio.length})`,
            show: props.portfolio.length > 0,
        },
        {
            id: 'atsiliepimai',
            label: `Atsiliepimai (${props.provider.reviews_count})`,
            show: true,
        },
    ].filter((section) => section.show),
);

// --- Etapas 6: „Pranešti" – tik prisijungusiems; savo profilio skųsti negalima (galutinai tikrina serveris) ---
const page = usePage();
// auth.user svečiui yra null, nors tipas to nerodo
const isLoggedIn = computed(() => Boolean(page.props.auth.user));
const canReportProfile = computed(
    () =>
        isLoggedIn.value &&
        page.props.auth.user.provider_profile?.id !== props.provider.id,
);

// Telefone apačioje lipni juosta su mygtuku – kad ji neuždengtų poraštės, puslapio apačioje paliekam vietos
const stickyBarPadding = ['pb-20', 'lg:pb-0'];

onMounted(() => document.body.classList.add(...stickyBarPadding));
onBeforeUnmount(() => document.body.classList.remove(...stickyBarPadding));
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="page-container pt-6 pb-20 md:pt-8">
        <Breadcrumbs :breadcrumbs="breadcrumbItems" />

        <!-- Viršelis: teikėjo nuotrauka arba spalvinis atsarginis dizainas -->
        <div
            class="relative mt-4 aspect-[16/7] overflow-hidden rounded-3xl bg-muted sm:aspect-[16/5] lg:aspect-auto lg:h-72"
        >
            <img
                v-if="provider.cover_url"
                :src="provider.cover_url"
                :alt="`${provider.display_name} – viršelio nuotrauka`"
                width="1600"
                height="500"
                fetchpriority="high"
                decoding="async"
                class="absolute inset-0 size-full object-cover"
            />
            <PhotoFallback v-else :tone="coverTone" icon-placement="none" />
        </div>

        <!-- Antraštė: kortelė „užlipa" ant viršelio -->
        <header
            class="relative mx-2 -mt-14 rounded-3xl border bg-card p-5 shadow-lift sm:mx-6 sm:-mt-20 sm:p-7 lg:mx-10"
        >
            <div
                class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between"
            >
                <div
                    class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:gap-6"
                >
                    <ProviderAvatar
                        :name="provider.display_name"
                        :src="provider.logo_url"
                        class="-mt-14 size-24 rounded-2xl shadow-lift ring-4 ring-card sm:-mt-16 sm:size-28"
                        text-class="text-3xl"
                    />
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <VerifiedBadge v-if="provider.is_verified" />
                            <!-- Etapas 9c: prenumeratos ženklelis -->
                            <ProBadge v-if="provider.has_pro_badge" />
                            <span
                                class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                >{{ provider.type }}</span
                            >
                        </div>
                        <h1
                            class="mt-2 text-3xl leading-tight font-bold text-balance sm:text-4xl"
                        >
                            {{ provider.display_name }}
                        </h1>
                        <p
                            v-if="provider.headline"
                            class="mt-1.5 text-lg text-muted-foreground"
                        >
                            {{ provider.headline }}
                        </p>

                        <div
                            class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm"
                        >
                            <a
                                v-if="provider.reviews_count > 0"
                                href="#atsiliepimai"
                                class="inline-flex items-center gap-1.5 hover:underline"
                            >
                                <RatingStars :rating="provider.rating_avg" />
                                <span class="font-semibold">{{
                                    formatRating(provider.rating_avg)
                                }}</span>
                                <span class="text-muted-foreground">
                                    ({{
                                        plural(provider.reviews_count, [
                                            'atsiliepimas',
                                            'atsiliepimai',
                                            'atsiliepimų',
                                        ])
                                    }})
                                </span>
                            </a>
                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 text-muted-foreground"
                            >
                                <Sparkle class="size-4" aria-hidden="true" />
                                Naujas teikėjas – dar nėra atsiliepimų
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 text-muted-foreground"
                            >
                                <MapPin class="size-4" aria-hidden="true" />
                                {{ provider.city.name }}
                            </span>
                            <span
                                v-if="provider.completed_jobs_count > 0"
                                class="inline-flex items-center gap-1.5 text-muted-foreground"
                            >
                                <BriefcaseBusiness
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{
                                    plural(provider.completed_jobs_count, [
                                        'atliktas darbas',
                                        'atlikti darbai',
                                        'atliktų darbų',
                                    ])
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="hidden shrink-0 flex-col gap-2 lg:flex lg:w-64 lg:items-stretch"
                >
                    <Button variant="cta" size="xl" as-child>
                        <a :href="createRequestUrl">
                            <Plus class="size-5" aria-hidden="true" />
                            Sukurti užklausą
                        </a>
                    </Button>
                    <p class="text-center text-xs text-muted-foreground">
                        Nemokamai gausite pasiūlymus ir iš kitų teikėjų
                    </p>
                </div>
            </div>

            <!-- Etapas 6: pranešti apie profilį (prisijungusiems, ne savininkui) -->
            <div v-if="canReportProfile" class="mt-4 flex justify-end">
                <ReportDialog type="provider_profile" :id="provider.id" />
            </div>
        </header>

        <!-- Skilčių meniu -->
        <nav
            class="sticky top-16 z-30 -mx-4 mt-8 border-b bg-background/90 px-4 backdrop-blur-md md:-mx-6 md:px-6 lg:top-[4.5rem] xl:-mx-8 xl:px-8"
            aria-label="Profilio skiltys"
        >
            <ul class="flex gap-1 overflow-x-auto py-2">
                <li v-for="section in sections" :key="section.id">
                    <a
                        :href="`#${section.id}`"
                        class="inline-flex rounded-full px-3.5 py-1.5 text-sm font-medium whitespace-nowrap text-foreground/75 transition-colors hover:bg-accent hover:text-foreground"
                        >{{ section.label }}</a
                    >
                </li>
            </ul>
        </nav>

        <div
            class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_21rem] lg:gap-12"
        >
            <div class="min-w-0 space-y-14">
                <section
                    v-if="provider.description"
                    id="apie"
                    class="scroll-mt-36"
                    aria-labelledby="about"
                >
                    <h2 id="about" class="text-2xl font-semibold">Apie</h2>
                    <p
                        class="mt-4 text-[1.0625rem] leading-relaxed whitespace-pre-line text-foreground/85"
                    >
                        {{ provider.description }}
                    </p>
                </section>

                <section
                    id="paslaugos"
                    class="scroll-mt-36"
                    aria-labelledby="services"
                >
                    <h2 id="services" class="text-2xl font-semibold">
                        Paslaugos ir kainos
                    </h2>
                    <ul
                        class="mt-5 divide-y overflow-hidden rounded-2xl border bg-card shadow-soft"
                    >
                        <li
                            v-for="service in provider.services"
                            :key="service.name"
                            class="flex items-center justify-between gap-4 px-5 py-4"
                        >
                            <Link
                                v-if="service.slug"
                                :href="categoryShow(service.slug)"
                                class="font-medium hover:text-primary hover:underline"
                                >{{ service.name }}</Link
                            >
                            <span v-else class="font-medium">{{
                                service.name
                            }}</span>
                            <span
                                v-if="service.price_from_cents !== null"
                                class="shrink-0 rounded-full bg-secondary px-3 py-1 text-right text-sm font-semibold text-secondary-foreground numeric"
                            >
                                {{
                                    formatPriceFrom(
                                        service.price_from_cents,
                                        service.price_unit,
                                    )
                                }}
                            </span>
                            <span
                                v-else
                                class="shrink-0 text-sm text-muted-foreground"
                                >Kaina sutartinė</span
                            >
                        </li>
                    </ul>
                </section>

                <section
                    v-if="portfolio.length"
                    id="darbai"
                    class="scroll-mt-36"
                    aria-labelledby="portfolio"
                >
                    <h2 id="portfolio" class="text-2xl font-semibold">
                        Atlikti darbai
                    </h2>
                    <PortfolioGallery class="mt-5" :items="portfolio" />
                </section>

                <section
                    id="atsiliepimai"
                    aria-labelledby="reviews"
                    class="scroll-mt-36"
                >
                    <h2 id="reviews" class="text-2xl font-semibold">
                        Atsiliepimai
                    </h2>

                    <p
                        v-if="!reviews.data.length"
                        class="mt-5 rounded-2xl border border-dashed bg-card/60 px-4 py-10 text-center text-muted-foreground"
                    >
                        Šis teikėjas dar neturi atsiliepimų.
                    </p>

                    <ul v-else class="mt-5 space-y-4">
                        <li
                            v-for="review in reviews.data"
                            :key="review.id"
                            class="rounded-2xl border bg-card p-5 shadow-soft sm:p-6"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <ProviderAvatar
                                        :name="review.author_name"
                                        :src="null"
                                        class="size-10 rounded-full"
                                        text-class="text-sm"
                                    />
                                    <div class="min-w-0">
                                        <p class="font-semibold">
                                            {{ review.author_name }}
                                        </p>
                                        <div
                                            class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                        >
                                            <RatingStars
                                                :rating="review.rating"
                                                class="size-3.5"
                                            />
                                            <time
                                                v-if="review.published_at"
                                                :datetime="review.published_at"
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    formatDate(
                                                        review.published_at,
                                                    )
                                                }}</time
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <Badge
                                        v-if="review.is_verified"
                                        variant="outline"
                                        class="hidden font-normal sm:inline-flex"
                                    >
                                        <ShieldCheck
                                            class="text-primary"
                                            aria-hidden="true"
                                        />
                                        Užsakyta per platformą
                                    </Badge>
                                    <!-- Etapas 6: darbas atliktas ne per platformą – teikėjo pakvietimu -->
                                    <Badge
                                        v-else
                                        variant="outline"
                                        class="hidden font-normal text-muted-foreground sm:inline-flex"
                                        title="Buvęs klientas, pakviestas teikėjo. Darbas užsakytas ne per platformą."
                                    >
                                        <UserPlus aria-hidden="true" />
                                        Pagal pakvietimą
                                    </Badge>
                                    <!-- Etapas 6: pranešti apie atsiliepimą -->
                                    <ReportDialog
                                        v-if="isLoggedIn"
                                        type="review"
                                        :id="review.id"
                                        compact
                                    />
                                </div>
                            </div>
                            <p class="mt-4 leading-relaxed whitespace-pre-line">
                                {{ review.comment }}
                            </p>
                            <p
                                class="mt-3 text-xs text-muted-foreground sm:hidden"
                            >
                                {{
                                    review.is_verified
                                        ? 'Užsakyta per platformą'
                                        : 'Pagal pakvietimą'
                                }}
                            </p>
                            <div
                                v-if="review.provider_reply"
                                class="mt-4 rounded-xl border-l-4 border-primary/40 bg-secondary/60 p-4 text-sm"
                            >
                                <p
                                    class="flex items-center gap-1.5 font-semibold"
                                >
                                    <MessageSquareReply
                                        class="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                    Teikėjo atsakymas
                                </p>
                                <p
                                    class="mt-1 whitespace-pre-line text-muted-foreground"
                                >
                                    {{ review.provider_reply }}
                                </p>
                            </div>
                        </li>
                    </ul>

                    <!-- Dalinis perkrovimas: keičiant puslapį iš serverio imami tik atsiliepimai -->
                    <CatalogPagination
                        class="mt-8"
                        :paginated="reviews"
                        :only="['reviews']"
                        preserve-scroll
                    />
                </section>
            </div>

            <aside class="space-y-5">
                <div class="space-y-5 lg:sticky lg:top-36">
                    <section
                        v-if="provider.reviews_count > 0"
                        class="rounded-2xl border bg-card p-5 shadow-soft"
                        aria-labelledby="rating-summary"
                    >
                        <h2
                            id="rating-summary"
                            class="font-sans text-base font-semibold tracking-normal"
                        >
                            Įvertinimas
                        </h2>
                        <div class="mt-3 flex items-center gap-4">
                            <span
                                class="font-display text-5xl font-semibold tracking-tight numeric"
                                >{{ formatRating(provider.rating_avg) }}</span
                            >
                            <div>
                                <RatingStars :rating="provider.rating_avg" />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{
                                        plural(provider.reviews_count, [
                                            'atsiliepimas',
                                            'atsiliepimai',
                                            'atsiliepimų',
                                        ])
                                    }}
                                </p>
                            </div>
                        </div>
                        <ul class="mt-5 space-y-2">
                            <li
                                v-for="bucket in ratingDistribution"
                                :key="bucket.rating"
                                class="flex items-center gap-2.5 text-xs"
                            >
                                <span
                                    class="inline-flex w-7 items-center gap-0.5 font-medium"
                                >
                                    {{ bucket.rating }}
                                    <Star
                                        class="size-3 fill-star text-star"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span
                                    class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                                >
                                    <span
                                        class="block h-full rounded-full bg-star"
                                        :style="{
                                            width: `${bucketPercent(bucket.count)}%`,
                                        }"
                                    />
                                </span>
                                <span
                                    class="w-8 text-right text-muted-foreground numeric"
                                    >{{ bucket.count }}</span
                                >
                            </li>
                        </ul>
                    </section>

                    <section
                        class="rounded-2xl border bg-card p-5 shadow-soft"
                        aria-labelledby="areas"
                    >
                        <h2
                            id="areas"
                            class="font-sans text-base font-semibold tracking-normal"
                        >
                            Aptarnaujamos vietos
                        </h2>
                        <p
                            v-if="provider.serves_whole_country"
                            class="mt-3 inline-flex items-center gap-1.5 text-sm"
                        >
                            <MapPin
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            Visa Lietuva
                        </p>
                        <ul v-else class="mt-3 flex flex-wrap gap-1.5">
                            <li
                                v-for="area in provider.service_areas"
                                :key="area.slug"
                                class="rounded-full bg-muted px-2.5 py-0.5 text-xs"
                            >
                                {{ area.name }}
                            </li>
                        </ul>
                    </section>

                    <section
                        class="rounded-2xl border bg-card p-5 shadow-soft"
                        aria-labelledby="details"
                    >
                        <h2
                            id="details"
                            class="font-sans text-base font-semibold tracking-normal"
                        >
                            Informacija
                        </h2>
                        <dl class="mt-3 space-y-2.5 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Teikėjas</dt>
                                <dd>{{ provider.type }}</dd>
                            </div>
                            <div
                                v-if="provider.years_experience"
                                class="flex justify-between gap-4"
                            >
                                <dt class="text-muted-foreground">Patirtis</dt>
                                <dd>{{ provider.years_experience }} m.</dd>
                            </div>
                            <div
                                v-if="provider.member_since"
                                class="flex justify-between gap-4"
                            >
                                <dt
                                    class="inline-flex items-center gap-1 text-muted-foreground"
                                >
                                    <CalendarDays
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Platformoje nuo
                                </dt>
                                <dd>{{ provider.member_since }} m.</dd>
                            </div>
                            <div
                                v-if="provider.website"
                                class="flex justify-between gap-4"
                            >
                                <dt class="text-muted-foreground">Svetainė</dt>
                                <dd class="min-w-0">
                                    <!-- nofollow ugc: vartotojų nuorodos neperduoda mūsų svetainės SEO „svorio" -->
                                    <a
                                        :href="provider.website"
                                        target="_blank"
                                        rel="nofollow ugc noopener noreferrer"
                                        class="inline-flex max-w-full items-center gap-1 text-primary hover:underline"
                                    >
                                        <span class="truncate">{{
                                            provider.website.replace(
                                                /^https?:\/\//,
                                                '',
                                            )
                                        }}</span>
                                        <ExternalLink
                                            class="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                    </a>
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </aside>
        </div>
    </div>

    <!-- Telefone: lipni juosta apačioje su pagrindiniu veiksmu -->
    <div
        class="fixed inset-x-0 bottom-0 z-30 border-t bg-card/95 px-4 py-3 shadow-float backdrop-blur-md lg:hidden"
    >
        <div class="mx-auto flex max-w-xl items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold">
                    {{ provider.display_name }}
                </p>
                <p
                    v-if="provider.reviews_count > 0"
                    class="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Star
                        class="size-3 fill-star text-star"
                        aria-hidden="true"
                    />
                    {{ formatRating(provider.rating_avg) }} ·
                    {{
                        plural(provider.reviews_count, [
                            'atsiliepimas',
                            'atsiliepimai',
                            'atsiliepimų',
                        ])
                    }}
                </p>
            </div>
            <Button variant="cta" size="lg" as-child>
                <a :href="createRequestUrl">Sukurti užklausą</a>
            </Button>
        </div>
    </div>
</template>
