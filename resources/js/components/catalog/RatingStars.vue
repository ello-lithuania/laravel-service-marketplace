<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';
import { formatRating } from '@/lib/format';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<{ rating: number; class?: string }>(), {
    class: 'size-4',
});

const filled = computed(() => Math.round(props.rating));
</script>

<template>
    <span
        class="inline-flex items-center gap-0.5"
        role="img"
        :aria-label="`Įvertinimas ${formatRating(rating)} iš 5`"
    >
        <Star
            v-for="index in 5"
            :key="index"
            :class="
                cn(
                    props.class,
                    index <= filled
                        ? 'fill-amber-400 text-amber-400'
                        : 'text-muted-foreground/40',
                )
            "
            aria-hidden="true"
        />
    </span>
</template>
