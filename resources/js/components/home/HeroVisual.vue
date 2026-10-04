<script setup lang="ts">
import { CalendarClock, CircleCheck, MapPin, Star, Wallet } from '@lucide/vue';
import ProviderAvatar from '@/components/catalog/ProviderAvatar.vue';
import PhotoSlot from '@/components/site/PhotoSlot.vue';
import { formatPriceFrom, formatRating, plural } from '@/lib/format';
import type { ProviderCard, SitePhotoData } from '@/types';

// Pradžios puslapio viršaus vaizdas mėlyname fone (Etapas 11, variantas A).
// Su nuotrauka – didelė suapvalinta nuotrauka ir „plaukiojančios" kortelės: geltonas ženklelis ir geriausiai
// įvertinto teikėjo kortelė su tikrais duomenimis. Be nuotraukos – iliustracija iš sąsajos elementų:
// užklausa ir į ją atsakę teikėjai. Abu variantai iš karto parodo, kaip platforma veikia.
defineProps<{
    photo: SitePhotoData;
    providers: ProviderCard[];
}>();
</script>

<template>
    <div class="relative">
        <!-- Su nuotrauka -->
        <template v-if="photo">
            <div
                class="relative aspect-[4/3] overflow-hidden rounded-[2.25rem] bg-white/10 shadow-[0_30px_80px_-24px_rgb(8_20_90/0.6)] sm:aspect-[16/10] lg:aspect-auto lg:h-[35rem]"
            >
                <PhotoSlot
                    :src="photo.url"
                    :alt="photo.alt ?? 'Meistras su įrankiais kliento namuose'"
                    :width="1600"
                    :height="2000"
                    eager
                    sizes="(min-width: 1024px) 40vw, 100vw"
                />
            </div>

            <div
                v-if="providers[0]"
                class="absolute right-4 bottom-4 left-4 rounded-2xl bg-card p-4 text-card-foreground shadow-float sm:right-auto sm:w-80 lg:bottom-16 lg:-left-12 lg:p-5"
            >
                <p
                    class="text-xs font-bold tracking-[0.06em] text-primary uppercase"
                >
                    Geriausiai įvertintas
                </p>
                <div class="mt-2.5 flex items-center gap-3">
                    <ProviderAvatar
                        :name="providers[0].display_name"
                        :src="providers[0].logo_url"
                        class="size-11 rounded-full"
                        text-class="text-sm"
                    />
                    <div class="min-w-0">
                        <p class="truncate font-bold">
                            {{ providers[0].display_name }}
                        </p>
                        <p class="truncate text-sm text-muted-foreground">
                            <template v-if="providers[0].categories[0]"
                                >{{ providers[0].categories[0].name }} ·
                            </template>
                            {{ providers[0].city }}
                        </p>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3">
                    <span
                        v-if="providers[0].price_from"
                        class="text-lg font-extrabold whitespace-nowrap numeric"
                        >{{
                            formatPriceFrom(
                                providers[0].price_from.cents,
                                providers[0].price_from.unit,
                            )
                        }}</span
                    >
                    <span
                        class="ml-auto inline-flex items-center gap-1 rounded-lg bg-cta/25 px-2.5 py-1.5 text-sm font-semibold whitespace-nowrap"
                    >
                        <Star
                            class="size-3.5 fill-star text-star"
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
                    </span>
                </div>
            </div>
        </template>

        <!-- Be nuotraukos: iliustracija iš sąsajos elementų -->
        <div
            v-else
            class="relative overflow-hidden rounded-[2.25rem] bg-white/10 p-5 ring-1 ring-white/15 sm:p-8"
            aria-hidden="true"
        >
            <div
                class="pointer-events-none absolute inset-0 pattern-dots text-white/[0.08]"
            />

            <div class="relative">
                <div
                    class="rounded-2xl bg-card p-5 text-card-foreground shadow-float"
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
                    <p class="mt-3 text-lg font-extrabold tracking-tight">
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
                    class="mt-6 mb-3 text-xs font-bold tracking-[0.12em] text-brand-muted uppercase"
                >
                    Gauti pasiūlymai
                </p>
                <ul class="space-y-2.5">
                    <li
                        v-for="(provider, index) in providers.slice(0, 3)"
                        :key="provider.id"
                        class="flex items-center gap-3 rounded-xl p-3 ring-1 ring-white/15"
                        :class="index === 0 ? 'bg-white/[0.18]' : 'bg-white/10'"
                    >
                        <ProviderAvatar
                            :name="provider.display_name"
                            :src="provider.logo_url"
                            class="size-10 rounded-full"
                            text-class="text-sm"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-white">
                                {{ provider.display_name }}
                            </p>
                            <p
                                class="flex items-center gap-1 text-xs text-brand-muted"
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

        <!-- Geltonas ženklelis – kaip greitai ateina pasiūlymai (tas pats teiginys kaip DUK skiltyje) -->
        <p
            class="absolute -top-4 right-4 rounded-2xl bg-cta px-4 py-3 text-sm font-extrabold text-cta-foreground shadow-float sm:-top-5 sm:text-base"
            :class="photo ? 'lg:top-10 lg:-right-4' : ''"
        >
            Pasiūlymai – dažnai tą pačią dieną
        </p>
    </div>
</template>
