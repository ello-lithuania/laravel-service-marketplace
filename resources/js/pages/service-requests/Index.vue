<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MapPin, MessageSquareText, Plus } from '@lucide/vue';
import PaginationLinks from '@/components/marketplace/PaginationLinks.vue';
import StatusBadge from '@/components/marketplace/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatBudget, formatDate, plural } from '@/lib/marketplace';
import { create, index, show } from '@/routes/service-requests';
import type { Paginated, ServiceRequestSummary } from '@/types';

defineProps<{
    serviceRequests: Paginated<ServiceRequestSummary>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mano užklausos', href: index() }],
    },
});
</script>

<template>
    <Head title="Mano užklausos" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Mano užklausos
                </h1>
                <p class="text-sm text-muted-foreground">
                    Čia matysite savo užklausas ir gautus pasiūlymus.
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"
                    ><Plus class="size-4" /> Nauja užklausa</Link
                >
            </Button>
        </header>

        <div
            v-if="serviceRequests.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center"
        >
            <p class="font-medium">Užklausų dar neturite</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Aprašykite darbą, o meistrai patys atsiųs pasiūlymus.
            </p>
            <Button class="mt-4" as-child>
                <Link :href="create()">Sukurti užklausą</Link>
            </Button>
        </div>

        <ul v-else class="space-y-3">
            <li v-for="request in serviceRequests.data" :key="request.id">
                <Link
                    :href="show(request)"
                    class="block rounded-xl border bg-card p-4 transition hover:border-primary/50 hover:shadow-sm"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-semibold">
                                {{ request.title }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ request.category?.name }}
                            </p>
                        </div>
                        <StatusBadge :status="request.status" />
                    </div>
                    <div
                        class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1">
                            <MapPin class="size-3.5" /> {{ request.city?.name }}
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <MessageSquareText class="size-3.5" />
                            {{ request.offers_count }}
                            {{
                                plural(
                                    request.offers_count,
                                    'pasiūlymas',
                                    'pasiūlymai',
                                    'pasiūlymų',
                                )
                            }}
                        </span>
                        <span>
                            {{
                                formatBudget(
                                    request.budget_min_cents,
                                    request.budget_max_cents,
                                )
                            }}
                        </span>
                        <span
                            >Sukurta {{ formatDate(request.created_at) }}</span
                        >
                    </div>
                </Link>
            </li>
        </ul>

        <PaginationLinks :paginator="serviceRequests" />
    </div>
</template>
