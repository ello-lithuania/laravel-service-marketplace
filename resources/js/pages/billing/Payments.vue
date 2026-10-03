<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import BillingStatusBadge from '@/components/billing/BillingStatusBadge.vue';
import PaginationLinks from '@/components/marketplace/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { formatPrice } from '@/lib/format';
import { formatDateTime } from '@/lib/marketplace';
import { pricing } from '@/routes';
import { index as creditsIndex } from '@/routes/credits';
import { index, invoice, show } from '@/routes/payments';
import type { Paginated, PaymentItem } from '@/types';

// Teikėjo mokėjimų istorija su sąskaitomis faktūromis (PaymentController@index)
defineProps<{
    payments: Paginated<PaymentItem>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Kreditai', href: creditsIndex() },
            { title: 'Mokėjimai', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Mokėjimai" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Mokėjimai</h1>
            <p class="text-sm text-muted-foreground">
                Visi jūsų mokėjimai ir sąskaitos faktūros.
            </p>
        </header>

        <div
            v-if="payments.data.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Mokėjimų dar nėra.
            <Link
                :href="pricing()"
                class="font-medium text-foreground underline underline-offset-4"
            >
                Peržiūrėkite kainas
            </Link>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">Data</th>
                        <th class="px-4 py-2 font-medium">Pirkinys</th>
                        <th class="px-4 py-2 text-right font-medium">Suma</th>
                        <th class="px-4 py-2 font-medium">Būsena</th>
                        <th class="px-4 py-2 font-medium">Sąskaita</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="payment in payments.data" :key="payment.uuid">
                        <td
                            class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                        >
                            {{
                                formatDateTime(
                                    payment.paid_at ?? payment.created_at,
                                )
                            }}
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                :href="show(payment.uuid)"
                                class="font-medium hover:underline"
                            >
                                {{ payment.description }}
                            </Link>
                            <p
                                v-if="payment.is_renewal"
                                class="text-xs text-muted-foreground"
                            >
                                Prenumeratos pratęsimas
                            </p>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            {{ formatPrice(payment.amount_cents) }}
                        </td>
                        <td class="px-4 py-3">
                            <BillingStatusBadge :status="payment.status" />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <!-- PDF – paprasta nuoroda (ne Inertia Link): atsisiunčiamas failas -->
                            <a
                                v-if="payment.can.download_invoice"
                                :href="invoice(payment.uuid).url"
                                class="inline-flex items-center gap-1 text-primary hover:underline"
                            >
                                <Download class="size-4" />
                                {{ payment.invoice_number }}
                            </a>
                            <Button
                                v-else-if="payment.can.pay"
                                size="sm"
                                variant="outline"
                                as-child
                            >
                                <Link :href="show(payment.uuid)">Apmokėti</Link>
                            </Button>
                            <span v-else class="text-muted-foreground">–</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationLinks :paginator="payments" />
    </div>
</template>
