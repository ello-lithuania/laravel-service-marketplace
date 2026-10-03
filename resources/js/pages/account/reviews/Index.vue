<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Copy, Link2, Star } from '@lucide/vue';
import { ref } from 'vue';
import ReportDialog from '@/components/complaints/ReportDialog.vue';
import InputError from '@/components/InputError.vue';
import FormTextarea from '@/components/marketplace/FormTextarea.vue';
import PaginationLinks from '@/components/marketplace/PaginationLinks.vue';
import ReviewCard from '@/components/reviews/ReviewCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDate, plural } from '@/lib/marketplace';
import { index, reply } from '@/routes/provider-reviews';
import type { AccountReview, Paginated } from '@/types';

defineProps<{
    reviews: Paginated<AccountReview>;
    summary: { rating_avg: number; reviews_count: number };
    invitation: { url: string; expires_at: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Atsiliepimai', href: index() }],
    },
});

const copied = ref(false);

async function copyLink(url: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Naršyklė neleido (pvz. ne HTTPS) – vartotojas gali nukopijuoti iš laukelio pats
    }
}

// Atsakymo forma – viena, rodoma prie pasirinkto atsiliepimo
const replyingTo = ref<number | null>(null);
const form = useForm({ reply: '' });

function openReply(id: number): void {
    replyingTo.value = id;
    form.reset();
    form.clearErrors();
}

function submitReply(id: number): void {
    form.post(reply(id).url, {
        preserveScroll: true,
        onSuccess: () => {
            replyingTo.value = null;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Atsiliepimai" />

    <div class="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Atsiliepimai
                </h1>
                <p class="text-sm text-muted-foreground">
                    Klientų atsiliepimai apie jus. Į kiekvieną galite viešai
                    atsakyti vieną kartą.
                </p>
            </div>
            <p
                v-if="summary.reviews_count > 0"
                class="inline-flex items-center gap-1.5 text-sm"
            >
                <Star class="size-4 fill-amber-400 text-amber-400" />
                <strong>{{ summary.rating_avg.toFixed(2) }}</strong>
                <span class="text-muted-foreground">
                    · {{ summary.reviews_count }}
                    {{
                        plural(
                            summary.reviews_count,
                            'atsiliepimas',
                            'atsiliepimai',
                            'atsiliepimų',
                        )
                    }}
                </span>
            </p>
        </header>

        <section class="space-y-3 rounded-xl border bg-card p-4 md:p-6">
            <h2 class="flex items-center gap-2 font-semibold">
                <Link2 class="size-4" /> Pakvieskite buvusius klientus
            </h2>
            <p class="text-sm text-muted-foreground">
                Dirbote su klientais ne per platformą? Nusiųskite jiems šią
                nuorodą – prisijungę jie galės palikti atsiliepimą (pažymimas
                „Pagal pakvietimą", skelbiamas po administratoriaus peržiūros).
                Nuoroda galioja iki {{ formatDate(invitation.expires_at) }}
            </p>
            <div class="flex gap-2">
                <Input
                    :model-value="invitation.url"
                    readonly
                    aria-label="Pakvietimo nuoroda"
                    data-test="invitation-url"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <Button
                    type="button"
                    variant="outline"
                    @click="copyLink(invitation.url)"
                >
                    <Check v-if="copied" class="size-4" />
                    <Copy v-else class="size-4" />
                    {{ copied ? 'Nukopijuota' : 'Kopijuoti' }}
                </Button>
            </div>
        </section>

        <p
            v-if="reviews.data.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Atsiliepimų dar nėra. Jie atsiranda, kai klientai pažymi darbą
            atliktu, arba pagal jūsų pakvietimą.
        </p>

        <ul v-else class="space-y-3">
            <li
                v-for="review in reviews.data"
                :key="review.id"
                class="rounded-xl border bg-card p-4"
            >
                <ReviewCard :review="review" reply-label="Jūsų atsakymas">
                    <div
                        class="flex flex-wrap items-start justify-between gap-2 pt-1"
                    >
                        <div v-if="review.can.reply" class="min-w-0 flex-1">
                            <Button
                                v-if="replyingTo !== review.id"
                                size="sm"
                                variant="outline"
                                data-test="reply-button"
                                @click="openReply(review.id)"
                            >
                                Atsakyti
                            </Button>
                            <form
                                v-else
                                class="space-y-2"
                                @submit.prevent="submitReply(review.id)"
                            >
                                <FormTextarea
                                    v-model="form.reply"
                                    :rows="3"
                                    :maxlength="2000"
                                    :invalid="!!form.errors.reply"
                                    placeholder="Padėkokite arba paaiškinkite situaciją. Atsakymą matys visi profilio lankytojai."
                                />
                                <InputError :message="form.errors.reply" />
                                <div class="flex gap-2">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="form.processing"
                                    >
                                        Paskelbti atsakymą
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        @click="replyingTo = null"
                                    >
                                        Atšaukti
                                    </Button>
                                </div>
                            </form>
                        </div>
                        <!-- Netikras ar įžeidžiantis atsiliepimas – pranešti administratoriui -->
                        <ReportDialog
                            type="review"
                            :id="review.id"
                            class="ml-auto"
                        />
                    </div>
                </ReviewCard>
            </li>
        </ul>

        <PaginationLinks :paginator="reviews" />
    </div>
</template>
