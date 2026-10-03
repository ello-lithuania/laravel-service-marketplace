<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { MessageSquare } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { show, store } from '@/routes/conversations';
import type { OfferMessaging } from '@/types';

// „Rašyti žinutę": jei pokalbis jau yra – nuoroda į jį, jei dar ne – POST jį sukuria (App\Support\OfferMessaging)
const props = withDefaults(
    defineProps<{
        offerId: number;
        messaging: OfferMessaging;
        size?: 'sm' | 'default';
        variant?: 'outline' | 'default' | 'secondary';
    }>(),
    { size: 'sm', variant: 'outline' },
);

const starting = ref(false);

function start(): void {
    router.post(
        store(props.offerId).url,
        {},
        {
            onStart: () => (starting.value = true),
            onFinish: () => (starting.value = false),
        },
    );
}
</script>

<template>
    <Button
        v-if="messaging.conversation_id"
        :size="size"
        :variant="variant"
        as-child
    >
        <Link
            :href="show(messaging.conversation_id)"
            data-test="open-conversation"
        >
            <MessageSquare class="size-4" /> Žinutės
        </Link>
    </Button>
    <Button
        v-else-if="messaging.can_start"
        :size="size"
        :variant="variant"
        :disabled="starting"
        data-test="start-conversation"
        @click="start"
    >
        <MessageSquare class="size-4" /> Rašyti žinutę
    </Button>
</template>
