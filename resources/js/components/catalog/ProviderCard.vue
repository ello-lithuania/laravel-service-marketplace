<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BadgeCheck, BriefcaseBusiness, MapPin, Star } from '@lucide/vue';
import ProBadge from '@/components/catalog/ProBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { getInitials } from '@/composables/useInitials';
import { formatPriceFrom, formatRating, plural } from '@/lib/format';
import { show } from '@/routes/providers';
import type { ProviderCard } from '@/types';

defineProps<{ provider: ProviderCard }>();
</script>

<template>
    <!-- Visa kortelė paspaudžiama: nuorodos ::after sluoksnis uždengia kortelę (relative) -->
    <article
        class="relative flex flex-col gap-4 rounded-lg border p-4 transition-colors hover:bg-accent/50 sm:flex-row"
    >
        <div class="flex min-w-0 flex-1 gap-4">
            <Avatar class="size-14 shrink-0 rounded-md">
                <AvatarImage
                    v-if="provider.logo_url"
                    :src="provider.logo_url"
                    :alt="provider.display_name"
                />
                <AvatarFallback
                    class="rounded-md bg-primary/10 font-semibold text-primary"
                >
                    {{ getInitials(provider.display_name) }}
                </AvatarFallback>
            </Avatar>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h3 class="font-semibold">
                        <Link
                            :href="show(provider.slug)"
                            class="after:absolute after:inset-0 hover:underline"
                        >
                            {{ provider.display_name }}
                        </Link>
                    </h3>
                    <Badge
                        v-if="provider.is_verified"
                        variant="secondary"
                        class="text-emerald-700 dark:text-emerald-400"
                    >
                        <BadgeCheck aria-hidden="true" />
                        Patikrintas
                    </Badge>
                    <!-- Etapas 9c: prenumeratos ženklelis -->
                    <ProBadge v-if="provider.has_pro_badge" />
                </div>

                <p
                    v-if="provider.headline"
                    class="mt-0.5 line-clamp-2 text-sm text-muted-foreground"
                >
                    {{ provider.headline }}
                </p>

                <div
                    class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                >
                    <span
                        v-if="provider.reviews_count > 0"
                        class="inline-flex items-center gap-1"
                    >
                        <Star
                            class="size-4 fill-amber-400 text-amber-400"
                            aria-hidden="true"
                        />
                        <span class="font-medium">{{
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
                    </span>
                    <span v-else class="text-muted-foreground"
                        >Dar nėra atsiliepimų</span
                    >

                    <span
                        class="inline-flex items-center gap-1 text-muted-foreground"
                    >
                        <MapPin class="size-4" aria-hidden="true" />
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
                        <BriefcaseBusiness class="size-4" aria-hidden="true" />
                        {{
                            plural(provider.completed_jobs_count, [
                                'atliktas darbas',
                                'atlikti darbai',
                                'atliktų darbų',
                            ])
                        }}
                    </span>
                </div>

                <div
                    v-if="provider.categories.length"
                    class="mt-3 flex flex-wrap gap-1.5"
                >
                    <Badge
                        v-for="category in provider.categories"
                        :key="category.slug"
                        variant="outline"
                        class="font-normal"
                    >
                        {{ category.name }}
                    </Badge>
                </div>
            </div>
        </div>

        <p
            v-if="provider.price_from"
            class="shrink-0 text-sm font-medium sm:text-right"
        >
            {{
                formatPriceFrom(
                    provider.price_from.cents,
                    provider.price_from.unit,
                )
            }}
        </p>
    </article>
</template>
