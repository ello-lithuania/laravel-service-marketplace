<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { CircleCheck, CircleX, Download, LoaderCircle } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import BillingStatusBadge from '@/components/billing/BillingStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatMoney } from '@/lib/marketplace';
import { pricing } from '@/routes';
import { index as creditsIndex } from '@/routes/credits';
import { index as paymentsIndex, invoice, pay } from '@/routes/payments';
import type { PaymentItem } from '@/types';

// Mokėjimo puslapis: čia grįžtama iš Paysera (accepturl) ir čia veda priminimo laiško nuoroda.
// Grįžimas iš Paysera dar nereiškia, kad pinigai gauti: tai patvirtina tiekėjo SERVERIS (callback'as).
// Todėl kol mokėjimas „Laukiama", puslapis kas 3 s perkrauna tik „payment" prop'ą (Inertia usePoll).
const props = defineProps<{ payment: PaymentItem }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Kreditai', href: creditsIndex() },
            { title: 'Mokėjimai', href: paymentsIndex() },
        ],
    },
});

const isPending = computed(() => props.payment.status.value === 'pending');

// Polling – ne ilgiau kaip 2 min.: jei patvirtinimo vis nėra, žmogus gali tiesiog grįžti vėliau
const { stop } = usePoll(
    3000,
    { only: ['payment'] },
    { autoStart: isPending.value },
);
const timeout = window.setTimeout(stop, 120_000);

watch(isPending, (pending) => {
    if (!pending) {
        stop();
    }
});

onUnmounted(() => window.clearTimeout(timeout));

const paying = ref(false);

function payNow(): void {
    router.post(
        pay(props.payment.uuid).url,
        {},
        {
            onStart: () => (paying.value = true),
            onFinish: () => (paying.value = false),
        },
    );
}
</script>

<template>
    <Head title="Mokėjimas" />

    <div class="mx-auto w-full max-w-xl space-y-6 p-4 md:p-6">
        <div class="rounded-xl border p-6 text-center">
            <template v-if="payment.status.value === 'paid'">
                <CircleCheck class="mx-auto size-12 text-emerald-600" />
                <h1 class="mt-3 text-xl font-semibold">Mokėjimas gautas</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Ačiū! Kreditai ar prenumerata jau jūsų paskyroje.
                </p>
            </template>
            <template v-else-if="isPending">
                <LoaderCircle
                    class="mx-auto size-12 animate-spin text-muted-foreground"
                />
                <h1 class="mt-3 text-xl font-semibold">
                    Laukiame mokėjimo patvirtinimo
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Jei ką tik apmokėjote, patvirtinimas paprastai ateina per
                    kelias sekundes – puslapis atsinaujins pats.
                </p>
            </template>
            <template v-else>
                <CircleX class="mx-auto size-12 text-red-600" />
                <h1 class="mt-3 text-xl font-semibold">
                    Mokėjimas {{ payment.status.label.toLowerCase() }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Pinigai nenuskaičiuoti. Galite bandyti dar kartą.
                </p>
            </template>
        </div>

        <dl class="divide-y rounded-xl border text-sm">
            <div class="flex justify-between gap-4 px-4 py-3">
                <dt class="text-muted-foreground">Pirkinys</dt>
                <dd class="text-right font-medium">
                    {{ payment.description }}
                </dd>
            </div>
            <div class="flex justify-between gap-4 px-4 py-3">
                <dt class="text-muted-foreground">Suma</dt>
                <dd class="font-medium">
                    {{ formatMoney(payment.amount_cents) }}
                </dd>
            </div>
            <div class="flex justify-between gap-4 px-4 py-3">
                <dt class="text-muted-foreground">Būsena</dt>
                <dd><BillingStatusBadge :status="payment.status" /></dd>
            </div>
            <div class="flex justify-between gap-4 px-4 py-3">
                <dt class="text-muted-foreground">Sukurta</dt>
                <dd>{{ formatDateTime(payment.created_at) }}</dd>
            </div>
            <div
                v-if="payment.paid_at"
                class="flex justify-between gap-4 px-4 py-3"
            >
                <dt class="text-muted-foreground">Apmokėta</dt>
                <dd>{{ formatDateTime(payment.paid_at) }}</dd>
            </div>
            <div class="flex justify-between gap-4 px-4 py-3">
                <dt class="text-muted-foreground">Mokėjimo būdas</dt>
                <dd>{{ payment.gateway.label }}</dd>
            </div>
        </dl>

        <div class="flex flex-wrap justify-center gap-2">
            <Button v-if="payment.can.pay" :disabled="paying" @click="payNow">
                Apmokėti {{ formatMoney(payment.amount_cents) }}
            </Button>
            <Button
                v-if="payment.can.download_invoice"
                variant="outline"
                as-child
            >
                <a :href="invoice(payment.uuid).url">
                    <Download class="size-4" /> Sąskaita
                    {{ payment.invoice_number }}
                </a>
            </Button>
            <Button
                v-if="['failed', 'cancelled'].includes(payment.status.value)"
                variant="outline"
                as-child
            >
                <Link :href="pricing()">Rinktis iš naujo</Link>
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="creditsIndex()">Į kreditų puslapį</Link>
            </Button>
        </div>
    </div>
</template>
