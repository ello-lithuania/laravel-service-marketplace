<script setup lang="ts">
import { CalendarClock, CircleCheck, MapPin, Star, Wallet } from '@lucide/vue';
import ProviderAvatar from '@/components/catalog/ProviderAvatar.vue';
import PhotoSlot from '@/components/site/PhotoSlot.vue';
import { formatRating, plural } from '@/lib/format';
import type { ProviderCard, SitePhotoData, SiteStats } from '@/types';

// Pradžios puslapio viršaus vaizdas. Su nuotrauka – nuotrauka ir „plaukiojančios" kortelės su tikrais duomenimis
// (geriausiai įvertintas teikėjas, vidutinis įvertinimas). Be nuotraukos – iliustracija iš sąsajos elementų:
// užklausa ir į ją atsakę teikėjai. Abu variantai iš karto parodo, kaip platforma veikia.
defineProps<{
    photo: SitePhotoData;
    providers: ProviderCard[];
    stats: SiteStats;
}>();
</script>

<template>
    <div class="relative">
        <!-- Su nuotrauka -->
        <template v-if="photo">
            <div
                class="relative aspect-[4/3] overflow-hidden rounded-[1.75rem] bg-muted shadow-float md:aspect-[16/10] lg:aspect-[4/5]"
            >
                <PhotoSlot
                    :src="photo.url"
                    :alt="photo.alt ?? 'Meistras dirba kliento namuose'"
                    :width="1600"
                    :height="2000"
                    eager
                    sizes="(min-width: 1024px) 40vw, 100vw"
                />
                <div
                    class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/45 to-transparent"
                    aria-hidden="true"
                />
            </div>

            <div
                v-if="stats.rating_avg !== null && stats.reviews > 0"
                class="absolute top-5 -left-3 flex items-center gap-3 rounded-2xl border bg-card/95 px-4 py-3 shadow-float backdrop-blur sm:-left-6 lg:top-10"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-star/15"
                >
                    <Star
                        class="size-5 fill-star text-star"
                        aria-hidden="true"
                    />
                </span>
                <span>
                    <span
                        class="block font-display text-xl leading-none font-semibold numeric"
                        >{{ formatRating(stats.rating_avg) }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >/ 5</span
                        ></span
                    >
                    <span class="mt-1 block text-xs text-muted-foreground"
                        >vidutinis klientų įvertinimas</span
                    >
                </span>
            </div>

            <div
                v-if="providers[0]"
                class="absolute -right-2 bottom-5 w-[17rem] rounded-2xl border bg-card/95 p-4 shadow-float backdrop-blur sm:-right-5 lg:right-auto lg:bottom-12 lg:-left-10"
            >
                <p
                    class="flex items-center gap-1.5 text-xs font-semibold text-primary"
                >
                    <span class="relative flex size-2">
                        <span
                            class="absolute inline-flex size-full animate-ping rounded-full bg-primary/50"
                        />
                        <span
                            class="relative inline-flex size-2 rounded-full bg-primary"
                        />
                    </span>
                    Geriausiai įvertintas teikėjas
                </p>
                <div class="mt-3 flex items-center gap-3">
                    <ProviderAvatar
                        :name="providers[0].display_name"
                        :src="providers[0].logo_url"
                        class="size-11 rounded-xl"
                        text-class="text-sm"
                    />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">
                            {{ providers[0].display_name }}
                        </p>
                        <p
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Star
                                class="size-3 fill-star text-star"
                                aria-hidden="true"
                            />
                            {{ formatRating(providers[0].rating_avg) }} ·
                            {{
                                plural(providers[0].reviews_count, [
                                    'atsiliepimas',
                                    'atsiliepimai',
                                    'atsiliepimų',
                                ])
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <!-- Be nuotraukos: iliustracija iš sąsajos elementų -->
        <div
            v-else
            class="relative overflow-hidden rounded-[1.75rem] bg-brand-deep p-5 text-brand-deep-foreground shadow-float sm:p-8"
            aria-hidden="true"
        >
            <div
                class="pointer-events-none absolute inset-0 pattern-dots text-white/[0.07]"
            />

            <div class="relative">
                <div
                    class="rounded-2xl bg-card p-5 text-card-foreground shadow-lift"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span
                            class="rounded-full bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground"
                            >Nauja užklausa</span
                        >
                        <span class="text-xs text-muted-foreground"
                            >prieš 12 min.</span
                        >
                    </div>
                    <p
                        class="mt-3 font-display text-lg font-semibold tracking-tight"
                    >
                        Vonios plytelių klijavimas
                    </p>
                    <ul
                        class="mt-3 grid grid-cols-2 gap-2 text-xs text-muted-foreground sm:grid-cols-3"
                    >
                        <li class="flex items-center gap-1.5">
                            <MapPin class="size-3.5" />
                            Kaunas
                        </li>
                        <li class="flex items-center gap-1.5">
                            <Wallet class="size-3.5" />
                            iki 900 €
                        </li>
                        <li class="flex items-center gap-1.5">
                            <CalendarClock class="size-3.5" />
                            per mėnesį
                        </li>
                    </ul>
                </div>

                <p
                    class="mt-6 mb-3 text-xs font-semibold tracking-[0.14em] text-brand-deep-muted uppercase"
                >
                    Gauti pasiūlymai
                </p>
                <ul class="space-y-2.5">
                    <li
                        v-for="(provider, index) in providers.slice(0, 3)"
                        :key="provider.id"
                        class="flex items-center gap-3 rounded-xl bg-white/[0.07] p-3 ring-1 ring-white/10"
                        :class="index === 0 ? 'bg-white/[0.12]' : ''"
                    >
                        <ProviderAvatar
                            :name="provider.display_name"
                            :src="provider.logo_url"
                            class="size-10 rounded-lg"
                            text-class="text-sm"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-semibold text-white"
                            >
                                {{ provider.display_name }}
                            </p>
                            <p
                                class="flex items-center gap-1 text-xs text-brand-deep-muted"
                            >
                                <Star class="size-3 fill-star text-star" />
                                {{ formatRating(provider.rating_avg) }} ·
                                {{ provider.city }}
                            </p>
                        </div>
                        <CircleCheck
                            v-if="index === 0"
                            class="size-5 shrink-0 text-cta"
                        />
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
