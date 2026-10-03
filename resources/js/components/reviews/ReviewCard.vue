<script setup lang="ts">
import { MessageSquareReply, ShieldCheck, UserPlus } from '@lucide/vue';
import RatingStars from '@/components/catalog/RatingStars.vue';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/marketplace';
import type { AccountReview } from '@/types';

// Vienas atsiliepimas paskyroje. Būsena (pvz. „Laukia moderavimo") rodoma tik kai atsiliepimas dar nepaskelbtas
defineProps<{
    review: AccountReview;
    replyLabel?: string;
    /** Užklausos puslapyje darbo pavadinimas jau matomas antraštėje */
    hideRequestTitle?: boolean;
}>();
</script>

<template>
    <article class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <RatingStars :rating="review.rating" />
                <span class="font-medium">{{ review.author_name }}</span>
                <Badge
                    v-if="review.is_verified"
                    variant="outline"
                    class="font-normal"
                >
                    <ShieldCheck aria-hidden="true" /> Užsakyta per platformą
                </Badge>
                <Badge v-else variant="outline" class="font-normal">
                    <UserPlus aria-hidden="true" /> Pagal pakvietimą
                </Badge>
                <Badge
                    v-if="review.status.value !== 'published'"
                    variant="secondary"
                >
                    {{ review.status.label }}
                </Badge>
            </div>
            <time
                v-if="review.published_at ?? review.created_at"
                class="text-xs text-muted-foreground"
            >
                {{ formatDate(review.published_at ?? review.created_at) }}
            </time>
        </div>
        <p
            v-if="review.service_request_title && !hideRequestTitle"
            class="text-xs text-muted-foreground"
        >
            Darbas: {{ review.service_request_title }}
        </p>
        <p class="text-sm whitespace-pre-line">{{ review.comment }}</p>
        <div
            v-if="review.provider_reply"
            class="rounded-md bg-muted/60 p-3 text-sm"
        >
            <p class="flex items-center gap-1.5 font-medium">
                <MessageSquareReply class="size-4" aria-hidden="true" />
                {{ replyLabel ?? 'Teikėjo atsakymas' }}
            </p>
            <p class="mt-1 whitespace-pre-line text-muted-foreground">
                {{ review.provider_reply }}
            </p>
        </div>
        <slot />
    </article>
</template>
