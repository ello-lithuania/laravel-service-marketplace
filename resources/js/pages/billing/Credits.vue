<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CalendarClock,
    Coins,
    Receipt,
    TriangleAlert,
} from '@lucide/vue';
import { ref } from 'vue';
import BillingStatusBadge from '@/components/billing/BillingStatusBadge.vue';
import PaginationLinks from '@/components/marketplace/PaginationLinks.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { usePurchase } from '@/composables/usePurchase';
import { formatCreditChange, formatCredits } from '@/lib/billing';
import { formatPrice } from '@/lib/format';
import { formatDate, formatDateTime } from '@/lib/marketplace';
import { pricing } from '@/routes';
import { index as creditsIndex } from '@/routes/credits';
import { index as paymentsIndex, pay } from '@/routes/payments';
import { cancel as cancelSubscription } from '@/routes/subscriptions';
import type {
    CreditPackage,
    CreditTransactionItem,
    Paginated,
    PaymentItem,
    ProviderSubscription,
} from '@/types';

// Teikėjo „Kreditai" (CreditsController): balansas, paketai, prenumerata, kreditų istorija (ledger).
const props = defineProps<{
    balance: number;
    lowCreditsThreshold: number;
    packages: CreditPackage[];
    subscription: ProviderSubscription | null;
    scheduledSubscription: ProviderSubscription | null;
    renewalPayment: PaymentItem | null;
    transactions: Paginated<CreditTransactionItem>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Kreditai', href: creditsIndex() }],
    },
});

const { processing, error, buyPackage } = usePurchase();
const busy = ref(false);

function payRenewal(): void {
    if (!props.renewalPayment) {
        return;
    }

    router.post(
        pay(props.renewalPayment.uuid).url,
        {},
        {
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}

function cancel(): void {
    if (
        !props.subscription ||
        !window.confirm(
            'Atšaukti prenumeratą? Ji galios iki apmokėto laikotarpio pabaigos, vėliau nebus pratęsiama.',
        )
    ) {
        return;
    }

    router.post(
        cancelSubscription(props.subscription.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}
</script>

<template>
    <Head title="Kreditai" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Kreditai</h1>
                <p class="text-sm text-muted-foreground">
                    Už kreditus siunčiate pasiūlymus klientams.
                </p>
            </div>
            <Button variant="outline" as-child>
                <Link :href="paymentsIndex()">
                    <Receipt class="size-4" /> Mokėjimai ir sąskaitos
                </Link>
            </Button>
        </header>

        <Alert v-if="error" variant="destructive">
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <div class="grid gap-4 md:grid-cols-2">
            <section class="rounded-xl border p-5" aria-label="Balansas">
                <p
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Coins class="size-4 text-amber-500" /> Kreditų likutis
                </p>
                <p class="mt-2 text-4xl font-semibold" data-test="balance">
                    {{ balance }}
                </p>
                <p
                    v-if="balance < lowCreditsThreshold"
                    class="mt-3 flex items-start gap-2 text-sm text-amber-700 dark:text-amber-300"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                    Kreditų liko mažai – papildykite, kad galėtumėte siųsti
                    pasiūlymus.
                </p>
            </section>

            <section class="rounded-xl border p-5" aria-label="Prenumerata">
                <p
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <BadgeCheck class="size-4 text-primary" /> Prenumerata
                </p>

                <template v-if="subscription">
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <p class="text-xl font-semibold">
                            {{ subscription.plan.name }}
                        </p>
                        <BillingStatusBadge :status="subscription.status" />
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            formatCredits(subscription.plan.credits_per_period)
                        }}
                        kas {{ subscription.plan.period_label }} ·
                        {{ formatPrice(subscription.plan.price_cents) }} /
                        {{ subscription.plan.period_label }}
                    </p>
                    <p class="mt-3 flex items-center gap-2 text-sm">
                        <CalendarClock class="size-4 text-muted-foreground" />
                        <span v-if="subscription.status.value === 'past_due'">
                            Laikotarpis baigėsi
                            {{ formatDate(subscription.ends_at) }} – apmokėkite
                            pratęsimą.
                        </span>
                        <span v-else-if="subscription.auto_renew">
                            Apmokėta iki {{ formatDate(subscription.ends_at) }}
                            – prieš pabaigą atsiųsime nuorodą pratęsti.
                        </span>
                        <span v-else>
                            Galioja iki {{ formatDate(subscription.ends_at) }},
                            nebus pratęsta.
                        </span>
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Button
                            v-if="renewalPayment?.can.pay"
                            :disabled="busy"
                            @click="payRenewal"
                        >
                            Apmokėti pratęsimą ({{
                                formatPrice(renewalPayment.amount_cents)
                            }})
                        </Button>
                        <Button
                            v-if="subscription.can_cancel"
                            variant="outline"
                            :disabled="busy"
                            data-test="cancel-subscription"
                            @click="cancel"
                        >
                            Atšaukti prenumeratą
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link :href="`${pricing.url()}#planai`"
                                >Keisti planą</Link
                            >
                        </Button>
                    </div>
                </template>

                <template v-else>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Prenumeratos neturite. Su ja kreditus gautumėte kas
                        mėnesį, pigiau nei perkant paketus.
                    </p>
                    <Button class="mt-4" variant="outline" as-child>
                        <Link :href="`${pricing.url()}#planai`"
                            >Peržiūrėti planus</Link
                        >
                    </Button>
                </template>

                <p
                    v-if="scheduledSubscription"
                    class="mt-4 rounded-md bg-muted px-3 py-2 text-sm"
                >
                    Nuo {{ formatDate(scheduledSubscription.starts_at) }} –
                    planas „{{ scheduledSubscription.plan.name }}".
                </p>
            </section>
        </div>

        <section aria-labelledby="buy-title">
            <h2 id="buy-title" class="text-lg font-semibold">Pirkti kreditų</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="pkg in packages"
                    :key="pkg.id"
                    class="flex flex-col rounded-xl border p-4"
                >
                    <p class="text-2xl font-semibold">
                        {{ pkg.total_credits }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >kred.</span
                        >
                    </p>
                    <p
                        v-if="pkg.bonus_credits > 0"
                        class="text-xs font-medium text-emerald-700 dark:text-emerald-300"
                    >
                        įskaitant +{{ pkg.bonus_credits }} dovanų
                    </p>
                    <p class="mt-2 font-medium">
                        {{ formatPrice(pkg.price_cents) }}
                    </p>
                    <Button
                        class="mt-3"
                        size="sm"
                        :disabled="processing !== null"
                        :data-test="`buy-package-${pkg.id}`"
                        @click="buyPackage(pkg.id)"
                    >
                        {{
                            processing === `package-${pkg.id}`
                                ? 'Nukreipiama…'
                                : 'Pirkti'
                        }}
                    </Button>
                </div>
            </div>
        </section>

        <section aria-labelledby="history-title">
            <h2 id="history-title" class="text-lg font-semibold">
                Kreditų istorija
            </h2>

            <div
                v-if="transactions.data.length === 0"
                class="mt-3 rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Kreditų operacijų dar nėra.
            </div>

            <div v-else class="mt-3 overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/50 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">Data</th>
                            <th class="px-4 py-2 font-medium">Operacija</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Kreditai
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Likutis
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="item in transactions.data" :key="item.id">
                            <td
                                class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(item.created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ item.type.label }}</p>
                                <p
                                    v-if="item.description"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ item.description }}
                                </p>
                            </td>
                            <td
                                class="px-4 py-3 text-right font-medium whitespace-nowrap"
                                :class="
                                    item.amount > 0
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : ''
                                "
                            >
                                {{ formatCreditChange(item.amount) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ item.balance_after }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationLinks :paginator="transactions" />
        </section>
    </div>
</template>
