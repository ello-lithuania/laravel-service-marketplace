<script setup lang="ts">
import {
    BadgeCheck,
    Bell,
    CalendarClock,
    CircleCheck,
    CircleX,
    ClipboardCheck,
    ClipboardList,
    Coins,
    FileText,
    Inbox,
    MessageSquare,
    MessageSquareReply,
    ShieldCheck,
    Receipt,
    Star,
    Undo2,
    UserCog,
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
        // Etapas 6
        case 'ReviewInvitation':
            return Star;
        // Etapas 7: mokėjimai ir kreditai
        case 'PaymentSucceeded':
            return Receipt;
        case 'SubscriptionExpiring':
            return CalendarClock;
        case 'LowCredits':
            return Coins;
        // --- Etapas 9b: grąžintas mokėjimas ---
        case 'PaymentRefunded':
            return Undo2;
        case 'ReviewReplied':
            return MessageSquareReply;
        case 'CompletionRequested':
        case 'CompletionReminder':
            return ClipboardCheck;
        case 'ComplaintResolved':
            return ShieldCheck;
        // --- Etapas 9c: administratorius pakeitė profilio būseną ---
        case 'ProviderStatusChanged':
            return UserCog;
        case 'ProviderVerified':
            return BadgeCheck;
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
