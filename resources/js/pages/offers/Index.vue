<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Coins } from '@lucide/vue';
import PaginationLinks from '@/components/marketplace/PaginationLinks.vue';
import StatusBadge from '@/components/marketplace/StatusBadge.vue';
import { formatMoney, timeAgo } from '@/lib/marketplace';
import { index } from '@/routes/offers';
import { index as feed } from '@/routes/provider-feed';
import { show } from '@/routes/service-requests';
import type { Offer, Paginated } from '@/types';

defineProps<{
    offers: Paginated<Offer> | null;
    creditsBalance: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mano pasiūlymai', href: index() }],
    },
});
</script>

<template>
    <Head title="Mano pasiūlymai" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Mano pasiūlymai
                </h1>
                <p class="text-sm text-muted-foreground">
                    Visi jūsų išsiųsti pasiūlymai ir jų būsena.
                </p>
            </div>
            <p
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm"
            >
                <Coins class="size-4 text-amber-500" />
                Kreditai: <strong>{{ creditsBalance }}</strong>
            </p>
        </header>

        <div
            v-if="!offers || offers.data.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Pasiūlymų dar nesiuntėte.
            <Link
                :href="feed()"
                class="font-medium text-foreground underline underline-offset-4"
            >
                Peržiūrėkite užklausų srautą
            </Link>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">Užklausa</th>
                        <th class="px-4 py-2 font-medium">Kaina</th>
                        <th class="px-4 py-2 font-medium">Kreditai</th>
                        <th class="px-4 py-2 font-medium">Būsena</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="offer in offers.data" :key="offer.id">
                        <td class="px-4 py-3">
                            <Link
                                v-if="offer.service_request"
                                :href="show(offer.service_request)"
                                class="font-medium hover:underline"
                            >
                                {{ offer.service_request.title }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ timeAgo(offer.created_at) }} ·
                                {{
                                    offer.viewed_at
                                        ? 'peržiūrėtas'
                                        : 'neperžiūrėtas'
                                }}
                            </p>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{
                                offer.price_cents !== null
                                    ? formatMoney(offer.price_cents)
                                    : 'Po apžiūros'
                            }}
                        </td>
                        <td class="px-4 py-3">{{ offer.credits_spent }}</td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="offer.status" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationLinks v-if="offers" :paginator="offers" />
    </div>
</template>
