<script setup lang="ts">
import { billingStatusClasses } from '@/lib/billing';
import type { EnumValue, PaymentStatus, SubscriptionStatus } from '@/types';

// Mokėjimo ar prenumeratos būsena – lietuviškas pavadinimas ateina iš serverio (enum label())
defineProps<{
    status: EnumValue<PaymentStatus | SubscriptionStatus>;
}>();
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="billingStatusClasses(status.value)"
    >
        {{ status.label }}
    </span>
</template>
