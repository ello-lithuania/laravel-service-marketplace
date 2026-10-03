<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BadgeCheck, CalendarDays, Clock, Star } from '@lucide/vue';
import { ref } from 'vue';
import StatusBadge from '@/components/marketplace/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatMoney, plural, timeAgo } from '@/lib/marketplace';
import { accept, decline } from '@/routes/offers';
import { index, show } from '@/routes/service-requests';
import type { Offer, ServiceRequestSummary } from '@/types';

const props = defineProps<{
    offer: Offer;
    serviceRequest: ServiceRequestSummary;
    can: { accept: boolean; decline: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mano užklausos', href: index() }],
    },
});

const busy = ref(false);

// Būsenos keitimas – POST, o serveris nukreipia atgal į užklausos puslapį su pranešimu (toast)
function act(action: 'accept' | 'decline'): void {
    const question =
        action === 'accept'
            ? 'Priimti šį pasiūlymą? Kiti pasiūlymai bus atmesti, o teikėjas gaus jūsų kontaktus.'
            : 'Atmesti šį pasiūlymą?';

    if (!window.confirm(question)) {
        return;
    }

    const route =
        action === 'accept' ? accept(props.offer.id) : decline(props.offer.id);

    router.post(
        route.url,
        {},
        {
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}
</script>

<template>
    <Head :title="`Pasiūlymas: ${offer.provider?.display_name ?? ''}`" />

    <div class="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
        <Link
            :href="show(serviceRequest)"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> {{ serviceRequest.title }}
        </Link>

        <section class="space-y-5 rounded-xl border bg-card p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-xl font-semibold">
                        {{ offer.provider?.display_name }}
                        <BadgeCheck
                            v-if="offer.provider?.is_verified"
                            class="size-5 text-sky-600"
                            aria-label="Patikrintas teikėjas"
                        />
                    </h1>
                    <p
                        v-if="offer.provider?.headline"
                        class="text-sm text-muted-foreground"
                    >
                        {{ offer.provider.headline }}
                    </p>
                    <p
                        v-if="offer.provider"
                        class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1">
                            <Star
                                class="size-3 fill-amber-400 text-amber-400"
                            />
                            {{ offer.provider.rating_avg.toFixed(1) }} ·
                            {{ offer.provider.reviews_count }}
                            {{
                                plural(
                                    offer.provider.reviews_count,
                                    'atsiliepimas',
                                    'atsiliepimai',
                                    'atsiliepimų',
                                )
                            }}
                        </span>
                        <span>
                            {{ offer.provider.completed_jobs_count }}
                            {{
                                plural(
                                    offer.provider.completed_jobs_count,
                                    'atliktas darbas',
                                    'atlikti darbai',
                                    'atliktų darbų',
                                )
                            }}
                        </span>
                        <span v-if="offer.provider.city">
                            {{ offer.provider.city }}
                        </span>
                    </p>
                </div>
                <StatusBadge :status="offer.status" />
            </div>

            <div
                class="grid gap-3 rounded-lg bg-muted/40 p-4 text-sm sm:grid-cols-3"
            >
                <div>
                    <p class="text-muted-foreground">Kaina</p>
                    <p class="text-lg font-semibold">
                        {{
                            offer.price_cents !== null
                                ? formatMoney(offer.price_cents)
                                : 'Po apžiūros'
                        }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ offer.price_type.label }}
                    </p>
                </div>
                <div>
                    <p
                        class="inline-flex items-center gap-1 text-muted-foreground"
                    >
                        <Clock class="size-3.5" /> Trukmė
                    </p>
                    <p class="font-medium">{{ offer.duration_text ?? '–' }}</p>
                </div>
                <div>
                    <p
                        class="inline-flex items-center gap-1 text-muted-foreground"
                    >
                        <CalendarDays class="size-3.5" /> Gali pradėti
                    </p>
                    <p class="font-medium">
                        {{
                            offer.start_date
                                ? formatDate(offer.start_date)
                                : '–'
                        }}
                    </p>
                </div>
            </div>

            <div>
                <p class="mb-1 text-sm font-medium">Žinutė</p>
                <p class="text-sm whitespace-pre-line">{{ offer.message }}</p>
                <p class="mt-2 text-xs text-muted-foreground">
                    Išsiųsta {{ timeAgo(offer.created_at) }}
                </p>
            </div>

            <div
                v-if="can.accept || can.decline"
                class="flex flex-wrap gap-3 border-t pt-4"
            >
                <Button
                    v-if="can.accept"
                    :disabled="busy"
                    data-test="accept-offer"
                    @click="act('accept')"
                >
                    Priimti pasiūlymą
                </Button>
                <Button
                    v-if="can.decline"
                    variant="outline"
                    :disabled="busy"
                    data-test="decline-offer"
                    @click="act('decline')"
                >
                    Atmesti
                </Button>
            </div>
        </section>
    </div>
</template>
