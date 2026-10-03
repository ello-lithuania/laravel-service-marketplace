<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BadgeCheck, Mail, Phone, Star } from '@lucide/vue';
import { ref } from 'vue';
import CancelRequestDialog from '@/components/marketplace/CancelRequestDialog.vue';
import RequestDetails from '@/components/marketplace/RequestDetails.vue';
import StatusBadge from '@/components/marketplace/StatusBadge.vue';
import MessageButton from '@/components/messages/MessageButton.vue';
import ReviewCard from '@/components/reviews/ReviewCard.vue';
import ReviewForm from '@/components/reviews/ReviewForm.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatMoney, plural, timeAgo } from '@/lib/marketplace';
import { show as showOffer } from '@/routes/offers';
import { store as storeReview } from '@/routes/reviews';
import { complete, index } from '@/routes/service-requests';
import type {
    AccountReview,
    Offer,
    OfferMessaging,
    ServiceRequestDetail,
} from '@/types';

type OfferRow = Offer & {
    can: { accept: boolean; decline: boolean };
    messaging: OfferMessaging;
};

const props = defineProps<{
    serviceRequest: ServiceRequestDetail;
    offers: OfferRow[];
    acceptedContact: {
        name: string;
        phone: string | null;
        email: string;
    } | null;
    can: { cancel: boolean; complete: boolean; review: boolean };
    review: AccountReview | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mano užklausos', href: index() }],
    },
});

const completing = ref(false);

function markCompleted(): void {
    if (!window.confirm('Pažymėti, kad darbas atliktas?')) {
        return;
    }

    router.post(
        complete(props.serviceRequest).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (completing.value = true),
            onFinish: () => (completing.value = false),
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
            <p class="text-sm text-muted-foreground">
                {{ serviceRequest.category?.name }} · sukurta
                {{ formatDate(serviceRequest.created_at) }}
                <template
                    v-if="
                        serviceRequest.status.value === 'open' &&
                        serviceRequest.expires_at
                    "
                >
                    · pasiūlymų laukiama iki
                    {{ formatDate(serviceRequest.expires_at) }}
                </template>
            </p>
        </header>

        <div
            v-if="serviceRequest.status.value === 'pending'"
            class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            Užklausa laukia administratoriaus patvirtinimo. Jei el. paštas dar
            nepatvirtintas – patvirtinkite jį, ir užklausa bus paskelbta
            automatiškai.
        </div>

        <div
            v-if="serviceRequest.cancellation_reason"
            class="rounded-lg border bg-muted/40 p-4 text-sm"
        >
            <span class="font-medium">Atšaukimo priežastis:</span>
            {{ serviceRequest.cancellation_reason }}
        </div>

        <RequestDetails :service-request="serviceRequest" />

        <section
            v-if="acceptedContact"
            class="space-y-2 rounded-xl border border-emerald-300 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10"
        >
            <h2 class="font-semibold">
                Išrinktas teikėjas: {{ acceptedContact.name }}
            </h2>
            <p class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <a
                    v-if="acceptedContact.phone"
                    :href="`tel:${acceptedContact.phone}`"
                    class="inline-flex items-center gap-1.5 underline-offset-4 hover:underline"
                >
                    <Phone class="size-4" /> {{ acceptedContact.phone }}
                </a>
                <a
                    :href="`mailto:${acceptedContact.email}`"
                    class="inline-flex items-center gap-1.5 underline-offset-4 hover:underline"
                >
                    <Mail class="size-4" /> {{ acceptedContact.email }}
                </a>
            </p>
        </section>

        <div v-if="can.complete || can.cancel" class="flex flex-wrap gap-3">
            <Button
                v-if="can.complete"
                :disabled="completing"
                data-test="complete-request-button"
                @click="markCompleted"
            >
                Darbas atliktas
            </Button>
            <CancelRequestDialog
                v-if="can.cancel"
                :service-request="serviceRequest"
            />
        </div>

        <!-- Etapas 6: atsiliepimas po atlikto darbo -->
        <section
            v-if="review || can.review"
            id="atsiliepimas"
            class="scroll-mt-6 space-y-3 rounded-xl border bg-card p-4 md:p-6"
        >
            <template v-if="review">
                <h2 class="text-lg font-semibold">Jūsų atsiliepimas</h2>
                <ReviewCard :review="review" />
            </template>
            <template v-else>
                <div>
                    <h2 class="text-lg font-semibold">
                        Įvertinkite
                        {{ acceptedContact?.name ?? 'teikėją' }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Atsiliepimas bus paskelbtas teikėjo profilyje kaip
                        „Užsakyta per platformą". Jūsų pavardė nerodoma.
                    </p>
                </div>
                <ReviewForm :action="storeReview(serviceRequest.slug).url" />
            </template>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">
                Pasiūlymai ({{ offers.length }})
            </h2>

            <p
                v-if="offers.length === 0"
                class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                <template v-if="serviceRequest.status.value === 'open'">
                    Pasiūlymų dar nėra. Tinkami teikėjai jau gavo pranešimą –
                    paprastai pirmieji pasiūlymai atkeliauja per kelias
                    valandas.
                </template>
                <template v-else>Pasiūlymų nebuvo.</template>
            </p>

            <ul class="space-y-3">
                <li
                    v-for="offer in offers"
                    :key="offer.id"
                    class="rounded-xl border bg-card p-4"
                    :class="{
                        'border-emerald-400': offer.status.value === 'accepted',
                    }"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <p class="flex items-center gap-1.5 font-semibold">
                                {{ offer.provider?.display_name }}
                                <BadgeCheck
                                    v-if="offer.provider?.is_verified"
                                    class="size-4 text-sky-600"
                                    aria-label="Patikrintas teikėjas"
                                />
                                <span
                                    v-if="
                                        !offer.viewed_at &&
                                        offer.status.value === 'pending'
                                    "
                                    class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground uppercase"
                                >
                                    Naujas
                                </span>
                            </p>
                            <p
                                class="flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="offer.provider?.reviews_count"
                                    class="inline-flex items-center gap-1"
                                >
                                    <Star
                                        class="size-3 fill-amber-400 text-amber-400"
                                    />
                                    {{ offer.provider.rating_avg.toFixed(1) }}
                                    ({{ offer.provider.reviews_count }})
                                </span>
                                <span v-if="offer.provider">
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
                                <span>{{ timeAgo(offer.created_at) }}</span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold">
                                {{
                                    offer.price_cents !== null
                                        ? formatMoney(offer.price_cents)
                                        : '–'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ offer.price_type.label }}
                            </p>
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-muted-foreground">
                        {{ offer.message }}
                    </p>

                    <div
                        class="mt-3 flex flex-wrap items-center justify-between gap-3"
                    >
                        <StatusBadge :status="offer.status" />
                        <div class="flex flex-wrap gap-2">
                            <!-- Etapas 6: žinutės -->
                            <MessageButton
                                :offer-id="offer.id"
                                :messaging="offer.messaging"
                            />
                            <Button size="sm" variant="outline" as-child>
                                <Link
                                    :href="
                                        showOffer({
                                            serviceRequest: serviceRequest.slug,
                                            offer: offer.id,
                                        })
                                    "
                                    data-test="open-offer"
                                >
                                    Peržiūrėti pasiūlymą
                                </Link>
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
