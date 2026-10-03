<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    BriefcaseBusiness,
    CalendarDays,
    ExternalLink,
    ImageIcon,
    MapPin,
    MessageSquareReply,
    ShieldCheck,
    Star,
} from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import RatingStars from '@/components/catalog/RatingStars.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/composables/useInitials';
import {
    formatDate,
    formatMonth,
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
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto max-w-6xl px-4 py-8 md:py-12">
        <Breadcrumbs :breadcrumbs="breadcrumbItems" />

        <!-- Viršelio nuotrauka (jei teikėjas įkėlė) -->
        <img
            v-if="provider.cover_url"
            :src="provider.cover_url"
            :alt="provider.display_name"
            class="mt-4 aspect-[3/1] w-full rounded-xl object-cover"
        />

        <!-- Antraštė -->
        <header
            class="mt-4 flex flex-col gap-6 rounded-xl border p-5 md:flex-row md:items-start md:justify-between md:p-6"
        >
            <div class="flex min-w-0 gap-4">
                <Avatar class="size-20 shrink-0 rounded-lg">
                    <AvatarImage
                        v-if="provider.logo_url"
                        :src="provider.logo_url"
                        :alt="provider.display_name"
                    />
                    <AvatarFallback
                        class="rounded-lg bg-primary/10 text-xl font-semibold text-primary"
                    >
                        {{ getInitials(provider.display_name) }}
                    </AvatarFallback>
                </Avatar>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ provider.display_name }}
                        </h1>
                        <Badge
                            v-if="provider.is_verified"
                            variant="secondary"
                            class="text-emerald-700 dark:text-emerald-400"
                        >
                            <BadgeCheck aria-hidden="true" />
                            Patikrintas
                        </Badge>
                    </div>
                    <p
                        v-if="provider.headline"
                        class="mt-1 text-muted-foreground"
                    >
                        {{ provider.headline }}
                    </p>

                    <div
                        class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm"
                    >
                        <span
                            v-if="provider.reviews_count > 0"
                            class="inline-flex items-center gap-1.5"
                        >
                            <RatingStars :rating="provider.rating_avg" />
                            <span class="font-medium">{{
                                formatRating(provider.rating_avg)
                            }}</span>
                            <a
                                href="#atsiliepimai"
                                class="text-muted-foreground hover:underline"
                            >
                                ({{
                                    plural(provider.reviews_count, [
                                        'atsiliepimas',
                                        'atsiliepimai',
                                        'atsiliepimų',
                                    ])
                                }})
                            </a>
                        </span>
                        <span v-else class="text-muted-foreground"
                            >Dar nėra atsiliepimų</span
                        >
                        <span
                            class="inline-flex items-center gap-1 text-muted-foreground"
                        >
                            <MapPin class="size-4" aria-hidden="true" />
                            {{ provider.city.name }}
                        </span>
                        <span
                            v-if="provider.completed_jobs_count > 0"
                            class="inline-flex items-center gap-1 text-muted-foreground"
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

            <div class="flex shrink-0 flex-col gap-2 md:items-end">
                <Button size="lg" as-child>
                    <a :href="createRequestUrl">Sukurti užklausą</a>
                </Button>
                <p class="text-xs text-muted-foreground md:text-right">
                    Nemokamai gausite pasiūlymus ir iš kitų teikėjų
                </p>
            </div>
        </header>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_20rem]">
            <div class="min-w-0 space-y-10">
                <section v-if="provider.description" aria-labelledby="about">
                    <h2 id="about" class="text-lg font-semibold">Apie</h2>
                    <p class="mt-3 whitespace-pre-line text-muted-foreground">
                        {{ provider.description }}
                    </p>
                </section>

                <section aria-labelledby="services">
                    <h2 id="services" class="text-lg font-semibold">
                        Paslaugos ir kainos
                    </h2>
                    <ul class="mt-3 divide-y rounded-lg border">
                        <li
                            v-for="service in provider.services"
                            :key="service.name"
                            class="flex items-center justify-between gap-4 px-4 py-3 text-sm"
                        >
                            <Link
                                v-if="service.slug"
                                :href="categoryShow(service.slug)"
                                class="font-medium hover:underline"
                                >{{ service.name }}</Link
                            >
                            <span v-else class="font-medium">{{
                                service.name
                            }}</span>
                            <span
                                v-if="service.price_from_cents !== null"
                                class="shrink-0 text-right"
                            >
                                {{
                                    formatPriceFrom(
                                        service.price_from_cents,
                                        service.price_unit,
                                    )
                                }}
                            </span>
                            <span v-else class="shrink-0 text-muted-foreground"
                                >Kaina sutartinė</span
                            >
                        </li>
                    </ul>
                </section>

                <section v-if="portfolio.length" aria-labelledby="portfolio">
                    <h2 id="portfolio" class="text-lg font-semibold">
                        Atlikti darbai
                    </h2>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <article
                            v-for="item in portfolio"
                            :key="item.id"
                            class="overflow-hidden rounded-lg border"
                        >
                            <!-- Nuotraukos atsiras Etape 3 (medialibrary); kol kas – vieta paveikslėliui -->
                            <img
                                v-if="item.images.length"
                                :src="item.images[0].thumb_url"
                                :alt="item.title"
                                class="aspect-video w-full object-cover"
                                loading="lazy"
                            />
                            <div
                                v-else
                                class="flex h-24 items-center justify-center bg-muted"
                            >
                                <ImageIcon
                                    class="size-8 text-muted-foreground/50"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="p-4">
                                <h3 class="font-medium">{{ item.title }}</h3>
                                <p
                                    class="mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground"
                                >
                                    <span v-if="item.category">{{
                                        item.category
                                    }}</span>
                                    <span v-if="item.city">{{
                                        item.city
                                    }}</span>
                                    <span v-if="item.completed_date">{{
                                        formatMonth(item.completed_date)
                                    }}</span>
                                </p>
                                <p
                                    v-if="item.description"
                                    class="mt-2 line-clamp-3 text-sm text-muted-foreground"
                                >
                                    {{ item.description }}
                                </p>
                            </div>
                        </article>
                    </div>
                </section>

                <section
                    id="atsiliepimai"
                    aria-labelledby="reviews"
                    class="scroll-mt-6"
                >
                    <h2 id="reviews" class="text-lg font-semibold">
                        Atsiliepimai
                    </h2>

                    <p
                        v-if="!reviews.data.length"
                        class="mt-3 rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        Šis teikėjas dar neturi atsiliepimų.
                    </p>

                    <ul v-else class="mt-3 space-y-4">
                        <li
                            v-for="review in reviews.data"
                            :key="review.id"
                            class="rounded-lg border p-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <RatingStars :rating="review.rating" />
                                    <span class="font-medium">{{
                                        review.author_name
                                    }}</span>
                                    <Badge
                                        v-if="review.is_verified"
                                        variant="outline"
                                        class="font-normal"
                                    >
                                        <ShieldCheck aria-hidden="true" />
                                        Užsakyta per platformą
                                    </Badge>
                                </div>
                                <time
                                    v-if="review.published_at"
                                    :datetime="review.published_at"
                                    class="text-xs text-muted-foreground"
                                    >{{ formatDate(review.published_at) }}</time
                                >
                            </div>
                            <p class="mt-2 text-sm whitespace-pre-line">
                                {{ review.comment }}
                            </p>
                            <div
                                v-if="review.provider_reply"
                                class="mt-3 rounded-md bg-muted/60 p-3 text-sm"
                            >
                                <p
                                    class="flex items-center gap-1.5 font-medium"
                                >
                                    <MessageSquareReply
                                        class="size-4"
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
                        class="mt-6"
                        :paginated="reviews"
                        :only="['reviews']"
                        preserve-scroll
                    />
                </section>
            </div>

            <aside class="space-y-6">
                <section
                    v-if="provider.reviews_count > 0"
                    class="rounded-lg border p-4"
                    aria-labelledby="rating-summary"
                >
                    <h2 id="rating-summary" class="font-semibold">
                        Įvertinimas
                    </h2>
                    <div class="mt-2 flex items-center gap-3">
                        <span class="text-4xl font-semibold">{{
                            formatRating(provider.rating_avg)
                        }}</span>
                        <div>
                            <RatingStars :rating="provider.rating_avg" />
                            <p class="text-xs text-muted-foreground">
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
                    <ul class="mt-4 space-y-1.5">
                        <li
                            v-for="bucket in ratingDistribution"
                            :key="bucket.rating"
                            class="flex items-center gap-2 text-xs"
                        >
                            <span class="inline-flex w-6 items-center gap-0.5">
                                {{ bucket.rating }}
                                <Star
                                    class="size-3 fill-amber-400 text-amber-400"
                                    aria-hidden="true"
                                />
                            </span>
                            <span
                                class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                            >
                                <span
                                    class="block h-full rounded-full bg-amber-400"
                                    :style="{
                                        width: `${bucketPercent(bucket.count)}%`,
                                    }"
                                />
                            </span>
                            <span
                                class="w-8 text-right text-muted-foreground"
                                >{{ bucket.count }}</span
                            >
                        </li>
                    </ul>
                </section>

                <section class="rounded-lg border p-4" aria-labelledby="areas">
                    <h2 id="areas" class="font-semibold">
                        Aptarnaujamos vietos
                    </h2>
                    <p
                        v-if="provider.serves_whole_country"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        Visa Lietuva
                    </p>
                    <ul v-else class="mt-2 flex flex-wrap gap-1.5">
                        <li
                            v-for="area in provider.service_areas"
                            :key="area.slug"
                        >
                            <Badge variant="outline" class="font-normal">{{
                                area.name
                            }}</Badge>
                        </li>
                    </ul>
                </section>

                <section
                    class="rounded-lg border p-4"
                    aria-labelledby="details"
                >
                    <h2 id="details" class="font-semibold">Informacija</h2>
                    <dl class="mt-2 space-y-2 text-sm">
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
            </aside>
        </div>
    </div>
</template>
