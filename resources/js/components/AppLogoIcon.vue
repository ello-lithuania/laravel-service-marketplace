<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Etapas 10: ženklas – stogas su varnele („patikimas darbas namuose"). Linijos, ne užpildas,
         todėl gerai matosi ir mažas (16 px), o spalva – currentColor (paveldima iš tėvo) -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.4"
        stroke-linecap="round"
        stroke-linejoin="round"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <path fill="none" d="M3.5 11.5 12 4.25l8.5 7.25" />
        <path fill="none" d="m8.25 14.25 2.75 2.75 5-5.25" />
    </svg>
</template>
