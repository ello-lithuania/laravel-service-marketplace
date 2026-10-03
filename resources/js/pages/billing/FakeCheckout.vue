<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { FlaskConical } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/marketplace';
import { complete } from '@/routes/payments/fake';
import type { PaymentItem } from '@/types';

// Netikras mokėjimų tiekėjas (PAYMENT_GATEWAY=fake): Paysera puslapio imitacija dev'ui.
// Mygtukas siunčia pasirašytą „callback'ą" per tą patį kodą, kaip tikras Paysera serveris.
const props = defineProps<{ payment: PaymentItem }>();

const form = useForm({ result: '' });

function submit(result: 'paid' | 'failed' | 'cancelled'): void {
    form.result = result;
    form.submit(complete(props.payment.uuid));
}
</script>

<template>
    <Head title="Testinis mokėjimas" />

    <div class="mx-auto w-full max-w-md p-4 md:p-6">
        <div class="rounded-xl border p-6">
            <div
                class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                <FlaskConical class="mt-0.5 size-4 shrink-0" />
                <p>
                    <strong>Testinis mokėjimų tiekėjas.</strong> Tikri pinigai
                    nenuskaičiuojami. Produkcijoje vietoj šio puslapio atsidaro
                    Paysera.
                </p>
            </div>

            <h1 class="mt-5 text-lg font-semibold">
                {{ payment.description }}
            </h1>
            <p class="mt-1 text-3xl font-semibold">
                {{ formatMoney(payment.amount_cents) }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Užsakymo Nr. {{ payment.uuid }}
            </p>

            <div class="mt-6 grid gap-2">
                <Button
                    :disabled="form.processing"
                    data-test="fake-pay"
                    @click="submit('paid')"
                >
                    Apmokėti
                </Button>
                <Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="submit('failed')"
                >
                    Mokėjimas nepavyko
                </Button>
                <Button
                    variant="ghost"
                    :disabled="form.processing"
                    @click="submit('cancelled')"
                >
                    Atšaukti
                </Button>
            </div>
        </div>
    </div>
</template>
