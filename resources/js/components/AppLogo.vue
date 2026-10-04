<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

// Paskyros šoninio meniu logotipas – tas pats geltonas ženklas kaip viešoje dalyje (site/BrandLogo)
const name = usePage().props.name;
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-[0.6rem] bg-cta text-cta-foreground"
    >
        <AppLogoIcon class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span
            class="mb-0.5 truncate font-display leading-tight font-bold tracking-tight"
            >{{ name }}</span
        >
    </div>
</template>
