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
    <!-- Laikinas logotipas (namelis) – bus pakeistas, kai bus galutinis dizainas -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            d="M12 2.5 1.5 11h3v10.5h6v-6.5h3v6.5h6V11h3L12 2.5Z"
        />
    </svg>
</template>
