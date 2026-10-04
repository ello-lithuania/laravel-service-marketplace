<script setup lang="ts">
import { computed } from 'vue';
import { getInitials } from '@/composables/useInitials';
import { toneFor } from '@/lib/brandTones';
import { cn } from '@/lib/utils';

// Teikėjo logotipas arba inicialai. Inicialų fonas – spalvos tonas pagal vardą: sąraše kortelės skiriasi
// (o ne 20 vienodų žalių kvadratėlių), o tas pats teikėjas visur tos pačios spalvos.
const props = withDefaults(
    defineProps<{
        name: string;
        src: string | null;
        class?: string;
        /** inicialų dydis */
        textClass?: string;
    }>(),
    { class: 'size-14 rounded-xl', textClass: 'text-lg' },
);

const tone = computed(() => toneFor(null, props.name));
</script>

<template>
    <span
        :class="
            cn(
                'relative flex shrink-0 items-center justify-center overflow-hidden bg-muted',
                props.class,
            )
        "
    >
        <img
            v-if="src"
            :src="src"
            :alt="`${name} logotipas`"
            width="160"
            height="160"
            loading="lazy"
            decoding="async"
            class="size-full object-cover"
        />
        <span
            v-else
            :class="
                cn(
                    'flex size-full items-center justify-center font-display font-semibold tracking-tight text-white',
                    textClass,
                )
            "
            :style="{
                backgroundImage: `linear-gradient(135deg, ${tone.from}, ${tone.to})`,
            }"
            aria-hidden="true"
            >{{ getInitials(name) }}</span
        >
    </span>
</template>
