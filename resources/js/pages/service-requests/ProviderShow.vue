<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Coins, Mail, Phone, User } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import FormTextarea from '@/components/marketplace/FormTextarea.vue';
import RequestDetails from '@/components/marketplace/RequestDetails.vue';
import StatusBadge from '@/components/marketplace/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatMoney, timeAgo } from '@/lib/marketplace';
import { store, withdraw } from '@/routes/offers';
import { index as feed } from '@/routes/provider-feed';
import type { Offer, ServiceRequestDetail } from '@/types';

const props = defineProps<{
    serviceRequest: ServiceRequestDetail;
    client: { public_name: string; phone: string | null; email: string | null };
    myOffer:
        | (Offer & { is_chosen: boolean; can: { withdraw: boolean } })
        | null;
    offerForm: {
        allowed: boolean;
        reason: string | null;
        cost: number;
        balance: number;
        priceTypes: { value: string; label: string }[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Užklausų srautas', href: feed() }],
    },
});

const form = useForm({
    message: '',
    price_type: 'fixed',
    price: '',
    duration_text: '',
    start_date: '',
});

const enoughCredits = computed(
    () => props.offerForm.balance >= props.offerForm.cost,
);

// Verslo taisyklių klaidos (SendOffer) ateina ne prie formos laukų, o raktais „credits" ir „offer"
const ruleError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return errors.credits ?? errors.offer;
});

function submit(): void {
    form.submit(store(props.serviceRequest), { preserveScroll: true });
}

const withdrawing = ref(false);

function withdrawOffer(): void {
    if (
        !props.myOffer ||
        !window.confirm('Atšaukti pasiūlymą? Kreditai negrąžinami.')
    ) {
        return;
    }

    router.post(
        withdraw(props.myOffer.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (withdrawing.value = true),
            onFinish: () => (withdrawing.value = false),
        },
    );
}
</script>

<template>
    <Head :title="serviceRequest.title" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <header class="space-y-2">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ serviceRequest.title }}
                </h1>
                <StatusBadge :status="serviceRequest.status" />
            </div>
            <p
                class="flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground"
            >
                <span>{{ serviceRequest.category?.name }}</span>
                <span
                    >· paskelbta
                    {{ timeAgo(serviceRequest.published_at) }}</span
                >
                <span>· {{ serviceRequest.offers_count }} pasiūl.</span>
                <span class="inline-flex items-center gap-1">
                    · <User class="size-3.5" /> {{ client.public_name }}
                </span>
            </p>
        </header>

        <RequestDetails :service-request="serviceRequest" />

        <!-- Išrinktam teikėjui – kliento kontaktai -->
        <section
            v-if="myOffer?.is_chosen"
            class="space-y-2 rounded-xl border border-emerald-300 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10"
        >
            <h2 class="font-semibold">
                Jūsų pasiūlymas priimtas! Kliento kontaktai:
            </h2>
            <p class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <a
                    v-if="client.phone"
                    :href="`tel:${client.phone}`"
                    class="inline-flex items-center gap-1.5 underline-offset-4 hover:underline"
                >
                    <Phone class="size-4" /> {{ client.phone }}
                </a>
                <a
                    v-if="client.email"
                    :href="`mailto:${client.email}`"
                    class="inline-flex items-center gap-1.5 underline-offset-4 hover:underline"
                >
                    <Mail class="size-4" /> {{ client.email }}
                </a>
            </p>
        </section>

        <!-- Mano pasiūlymas -->
        <section
            v-if="myOffer"
            class="space-y-3 rounded-xl border bg-card p-4 md:p-6"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Jūsų pasiūlymas</h2>
                <StatusBadge :status="myOffer.status" />
            </div>
            <p class="text-sm">
                <strong>{{
                    myOffer.price_cents !== null
                        ? formatMoney(myOffer.price_cents)
                        : 'Po apžiūros'
                }}</strong>
                <span class="text-muted-foreground">
                    · {{ myOffer.price_type.label }}</span
                >
                <span
                    v-if="myOffer.duration_text"
                    class="text-muted-foreground"
                >
                    · {{ myOffer.duration_text }}
                </span>
                <span v-if="myOffer.start_date" class="text-muted-foreground">
                    · nuo {{ formatDate(myOffer.start_date) }}
                </span>
            </p>
            <p class="text-sm whitespace-pre-line">{{ myOffer.message }}</p>
            <p class="text-xs text-muted-foreground">
                Išsiųsta {{ timeAgo(myOffer.created_at) }} · kainavo
                {{ myOffer.credits_spent }} kred. ·
                {{
                    myOffer.viewed_at
                        ? `klientas peržiūrėjo ${timeAgo(myOffer.viewed_at)}`
                        : 'klientas dar neperžiūrėjo'
                }}
            </p>
            <Button
                v-if="myOffer.can.withdraw"
                variant="outline"
                size="sm"
                :disabled="withdrawing"
                data-test="withdraw-offer"
                @click="withdrawOffer"
            >
                Atšaukti pasiūlymą
            </Button>
        </section>

        <!-- Pasiūlymo forma -->
        <section
            v-else-if="offerForm.allowed"
            class="space-y-5 rounded-xl border bg-card p-4 md:p-6"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Siųsti pasiūlymą</h2>
                <p
                    class="inline-flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <Coins class="size-4 text-amber-500" />
                    Kainuoja
                    <strong class="text-foreground"
                        >{{ offerForm.cost }} kred.</strong
                    >
                    · turite {{ offerForm.balance }}
                </p>
            </div>

            <div
                v-if="!enoughCredits"
                class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                Nepakanka kreditų šiam pasiūlymui. Kreditų įsigyti galėsite
                skiltyje „Kainos" (netrukus).
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="message">Žinutė klientui</Label>
                    <FormTextarea
                        id="message"
                        v-model="form.message"
                        :rows="6"
                        :maxlength="3000"
                        :invalid="!!form.errors.message"
                        placeholder="Prisistatykite, kaip atliktumėte darbą, kas įskaičiuota į kainą."
                    />
                    <InputError :message="form.errors.message" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="price_type">Kainos tipas</Label>
                        <select
                            id="price_type"
                            v-model="form.price_type"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        >
                            <option
                                v-for="type in offerForm.priceTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.price_type" />
                    </div>
                    <div
                        v-if="form.price_type !== 'after_inspection'"
                        class="grid gap-2"
                    >
                        <Label for="price">Kaina (€)</Label>
                        <Input
                            id="price"
                            v-model="form.price"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            :aria-invalid="!!form.errors.price || undefined"
                        />
                        <InputError :message="form.errors.price" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="duration_text">Trukmė (neprivaloma)</Label>
                        <Input
                            id="duration_text"
                            v-model="form.duration_text"
                            maxlength="100"
                            placeholder="Pvz. 2–3 darbo dienos"
                        />
                        <InputError :message="form.errors.duration_text" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="start_date"
                            >Galiu pradėti nuo (neprivaloma)</Label
                        >
                        <Input
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                        />
                        <InputError :message="form.errors.start_date" />
                    </div>
                </div>

                <InputError :message="ruleError" />

                <Button
                    type="submit"
                    :disabled="form.processing || !enoughCredits"
                    data-test="send-offer"
                >
                    Siųsti pasiūlymą ({{ offerForm.cost }} kred.)
                </Button>
            </form>
        </section>

        <p
            v-else
            class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ offerForm.reason }}
        </p>
    </div>
</template>
