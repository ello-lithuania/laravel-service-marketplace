<script setup lang="ts">
import {
    Bell,
    CalendarClock,
    CircleCheck,
    CircleX,
    ClipboardList,
    Coins,
    FileText,
    Inbox,
    MessageSquare,
    Receipt,
    Star,
} from '@lucide/vue';
import { computed } from 'vue';

// Ikona pagal pranešimo tipą (Notification klasės vardą)
const props = defineProps<{ type: string }>();

const icon = computed(() => {
    switch (props.type) {
        case 'NewMatchingRequest':
            return Inbox;
        case 'NewOffer':
            return FileText;
        case 'OfferAccepted':
        case 'ServiceRequestPublished':
            return CircleCheck;
        case 'OfferDeclined':
        case 'ServiceRequestRejected':
        case 'ServiceRequestCancelled':
            return CircleX;
        case 'NewMessage':
            return MessageSquare;
        case 'NewReview':
            return Star;
        // Etapas 7: mokėjimai ir kreditai
        case 'PaymentSucceeded':
            return Receipt;
        case 'SubscriptionExpiring':
            return CalendarClock;
        case 'LowCredits':
            return Coins;
        default:
            return props.type.startsWith('ServiceRequest')
                ? ClipboardList
                : Bell;
    }
});
</script>

<template>
    <component :is="icon" class="size-4 shrink-0" />
</template>
