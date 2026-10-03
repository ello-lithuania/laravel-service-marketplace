<script setup lang="ts">
import { statusClasses } from '@/lib/marketplace';
import type { EnumValue, OfferStatus, ServiceRequestStatus } from '@/types';

defineProps<{
    status: EnumValue<ServiceRequestStatus | OfferStatus>;
}>();
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="statusClasses(status.value)"
    >
        {{ status.label }}
    </span>
</template>
