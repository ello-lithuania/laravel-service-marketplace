<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Coins, MapPin, MessageSquareText } from '@lucide/vue';
import { reactive, watch } from 'vue';
import PaginationLinks from '@/components/marketplace/PaginationLinks.vue';
import { Label } from '@/components/ui/label';
import { formatBudget, plural, timeAgo } from '@/lib/marketplace';
import { index } from '@/routes/provider-feed';
import { show } from '@/routes/service-requests';
import type { Option, Paginated, ServiceRequestSummary } from '@/types';

type Filters = {
    kategorija: number | null;
    miestas: number | null;
    laikotarpis: number | null;
};

const props = defineProps<{
    serviceRequests: Paginated<ServiceRequestSummary> | null;
    filters: Filters;
    options: { categories: Option[]; cities: Option[]; periods: number[] };
    provider: {
        status: { value: string; label: string };
        credits_balance: number;
        serves_whole_country: boolean;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Užklausų srautas', href: index() }],
    },
});

const form = reactive<Filters>({ ...props.filters });

// Pakeitus filtrą – nauja užklausa su ?kategorija=…; preserveState išlaiko formos būseną, replace – istoriją švarią
watch(form, () => {
    const query = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value !== null),
    );

    router.get(
        index.url({ query }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head title="Užklausų srautas" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Užklausų srautas
                </h1>
                <p class="text-sm text-muted-foreground">
                    Atviros užklausos jūsų paslaugų srityse ir aptarnavimo
                    zonose, kurioms dar nesiuntėte pasiūlymo.
                </p>
            </div>
            <p
                v-if="provider"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm"
            >
                <Coins class="size-4 text-amber-500" />
                Kreditai: <strong>{{ provider.credits_balance }}</strong>
            </p>
        </header>

        <div
            v-if="!provider"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Pirmiausia užpildykite teikėjo profilį: pasirinkite paslaugas ir
            aptarnavimo zonas.
        </div>

        <div
            v-else-if="provider.status.value !== 'active'"
            class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            Jūsų profilio būsena – „{{ provider.status.label }}". Užklausas
            matysite, kai profilis bus aktyvus.
        </div>

        <template v-else-if="serviceRequests">
            <div
                class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-3"
            >
                <div class="grid gap-1.5">
                    <Label for="kategorija">Paslauga</Label>
                    <select
                        id="kategorija"
                        v-model="form.kategorija"
                        :class="selectClass"
                    >
                        <option :value="null">Visos mano paslaugos</option>
                        <option
                            v-for="category in options.categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="miestas">Miestas / rajonas</Label>
                    <select
                        id="miestas"
                        v-model="form.miestas"
                        :class="selectClass"
                    >
                        <option :value="null">
                            {{
                                provider.serves_whole_country
                                    ? 'Visa Lietuva'
                                    : 'Visos mano zonos'
                            }}
                        </option>
                        <option
                            v-for="city in options.cities"
                            :key="city.id"
                            :value="city.id"
                        >
                            {{ city.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="laikotarpis">Paskelbta</Label>
                    <select
                        id="laikotarpis"
                        v-model="form.laikotarpis"
                        :class="selectClass"
                    >
                        <option :value="null">Bet kada</option>
                        <option
                            v-for="days in options.periods"
                            :key="days"
                            :value="days"
                        >
                            {{
                                days === 1
                                    ? 'Per paskutinę parą'
                                    : `Per paskutines ${days} d.`
                            }}
                        </option>
                    </select>
                </div>
            </div>

            <p
                v-if="serviceRequests.data.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Šiuo metu tinkamų užklausų nėra. Apie naujas pranešime varpelyje
                ir el. paštu.
            </p>

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
                                <p class="font-semibold">{{ request.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ request.category?.name }}
                                </p>
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ timeAgo(request.published_at) }}
                            </span>
                        </div>
                        <p
                            class="mt-2 line-clamp-2 text-sm text-muted-foreground"
                        >
                            {{ request.excerpt }}
                        </p>
                        <div
                            class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1">
                                <MapPin class="size-3.5" />
                                {{ request.city?.name }}
                            </span>
                            <span>{{
                                formatBudget(
                                    request.budget_min_cents,
                                    request.budget_max_cents,
                                )
                            }}</span>
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
                            <span class="inline-flex items-center gap-1">
                                <Coins class="size-3.5" />
                                {{ request.category?.offer_cost_credits }} kred.
                            </span>
                        </div>
                    </Link>
                </li>
            </ul>

            <PaginationLinks :paginator="serviceRequests" />
        </template>
    </div>
</template>
