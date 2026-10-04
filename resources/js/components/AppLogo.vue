<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

// Paskyros šoninio meniu logotipas – tas pats ženklas kaip viešoje dalyje (site/BrandLogo), tik su sidebar spalvomis
const name = usePage().props.name;
</script>

<template>
    <div
        class="relative flex aspect-square size-8 items-center justify-center rounded-[0.6rem] bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5" />
        <span
            class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-cta ring-2 ring-sidebar"
        />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span
            class="mb-0.5 truncate font-display leading-tight font-semibold tracking-tight"
            >{{ name }}</span
        >
    </div>
</template>
