<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BriefcaseBusiness,
    MapPin,
    Sparkle,
    Star,
} from '@lucide/vue';
import ProBadge from '@/components/catalog/ProBadge.vue';
import ProviderAvatar from '@/components/catalog/ProviderAvatar.vue';
import VerifiedBadge from '@/components/catalog/VerifiedBadge.vue';
import { formatPriceFrom, formatRating, plural } from '@/lib/format';
import { show } from '@/routes/providers';
import type { ProviderCard } from '@/types';

// Teikėjo kortelė. row – sąrašuose (katalogas, paieška), tile – pradžios puslapio tinklelyje.
withDefaults(
    defineProps<{ provider: ProviderCard; layout?: 'row' | 'tile' }>(),
    {
        layout: 'row',
    },
);
</script>

<template>
    <!-- Visa kortelė paspaudžiama: nuorodos ::after sluoksnis uždengia kortelę (relative) -->
    <article
        class="group relative flex rounded-2xl border bg-card shadow-soft transition-[box-shadow,border-color,transform] duration-300 focus-within:border-primary/40 focus-within:shadow-lift hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lift"
        :class="
            layout === 'tile'
                ? 'h-full flex-col p-5'
                : 'flex-col gap-4 p-4 sm:flex-row sm:p-5'
        "
    >
        <div class="flex min-w-0 flex-1 gap-4">
            <ProviderAvatar
                :name="provider.display_name"
                :src="provider.logo_url"
                :class="
                    layout === 'tile'
                        ? 'size-14 rounded-xl'
                        : 'size-14 rounded-xl sm:size-16'
                "
            />

            <div class="min-w-0 flex-1">
                <h3
                    class="font-display text-[1.0625rem] leading-snug font-semibold tracking-tight"
                >
                    <Link
                        :href="show(provider.slug)"
                        class="rounded-sm after:absolute after:inset-0 after:rounded-2xl focus-visible:outline-none"
                    >
                        {{ provider.display_name }}
                    </Link>
                </h3>

                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    <VerifiedBadge v-if="provider.is_verified" />
                    <!-- Etapas 9c: prenumeratos ženklelis -->
                    <ProBadge v-if="provider.has_pro_badge" />
                    <span
                        v-if="provider.reviews_count === 0"
                        class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                    >
                        <Sparkle class="size-3" aria-hidden="true" />
                        Naujas teikėjas
                    </span>
                </div>

                <template v-if="layout === 'row'">
                    <p
                        v-if="provider.headline"
                        class="mt-2 line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ provider.headline }}
                    </p>
                    <ul
                        v-if="provider.categories.length"
                        class="mt-3 flex flex-wrap gap-1.5"
                        aria-label="Paslaugos"
                    >
                        <li
                            v-for="category in provider.categories"
                            :key="category.slug"
                            class="rounded-full bg-muted px-2.5 py-0.5 text-xs text-muted-foreground"
                        >
                            {{ category.name }}
                        </li>
                    </ul>
                </template>
            </div>
        </div>

        <p
            v-if="provider.headline && layout === 'tile'"
            class="mt-4 line-clamp-2 text-sm text-muted-foreground"
        >
            {{ provider.headline }}
        </p>

        <div
            class="flex flex-col gap-3"
            :class="
                layout === 'tile'
                    ? 'mt-4 flex-1 justify-end'
                    : 'sm:w-56 sm:shrink-0 sm:items-end sm:justify-between sm:text-right'
            "
        >
            <div
                class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm"
                :class="layout === 'row' ? 'sm:justify-end' : ''"
            >
                <span
                    v-if="provider.reviews_count > 0"
                    class="inline-flex items-center gap-1 rounded-full bg-star/12 px-2 py-0.5 font-semibold text-foreground"
                >
                    <Star
                        class="size-3.5 fill-star text-star"
                        aria-hidden="true"
                    />
                    {{ formatRating(provider.rating_avg) }}
                    <span class="font-normal text-muted-foreground">
                        ({{
                            plural(provider.reviews_count, [
                                'atsiliepimas',
                                'atsiliepimai',
                                'atsiliepimų',
                            ])
                        }})
                    </span>
                </span>

                <span
                    class="inline-flex items-center gap-1 text-muted-foreground"
                >
                    <MapPin class="size-3.5" aria-hidden="true" />
                    {{
                        provider.serves_whole_country
                            ? 'Visa Lietuva'
                            : provider.city
                    }}
                </span>

                <span
                    v-if="provider.completed_jobs_count > 0"
                    class="inline-flex items-center gap-1 text-muted-foreground"
                >
                    <BriefcaseBusiness class="size-3.5" aria-hidden="true" />
                    {{
                        plural(provider.completed_jobs_count, [
                            'darbas',
                            'darbai',
                            'darbų',
                        ])
                    }}
                </span>
            </div>

            <p
                v-if="provider.price_from"
                class="font-display text-lg font-semibold tracking-tight text-foreground numeric"
            >
                {{
                    formatPriceFrom(
                        provider.price_from.cents,
                        provider.price_from.unit,
                    )
                }}
            </p>

            <span
                v-if="layout === 'row'"
                class="hidden items-center gap-1 text-sm font-medium text-primary sm:inline-flex"
                aria-hidden="true"
            >
                Žiūrėti profilį
                <ArrowUpRight
                    class="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                />
            </span>
        </div>

        <ul
            v-if="layout === 'tile' && provider.categories.length"
            class="mt-4 flex flex-wrap gap-1.5 border-t pt-4"
            aria-label="Paslaugos"
        >
            <li
                v-for="category in provider.categories"
                :key="category.slug"
                class="rounded-full bg-muted px-2.5 py-0.5 text-xs text-muted-foreground"
            >
                {{ category.name }}
            </li>
        </ul>
    </article>
</template>
