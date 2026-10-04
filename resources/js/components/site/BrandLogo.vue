<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { cn } from '@/lib/utils';

// Logotipas: ženklas + pavadinimas iš config('app.name') (galutinis vardas dar nepasirinktas).
// Etapas 11 (variantas A): geltonas ženklas su tamsiu stogu ir varnele, pavadinimas – vienas storas „wordmark".
// Geltona gerai matosi ir mėlynoje antraštėje, ir tamsioje poraštėje, ir baltame fone.
const props = withDefaults(
    defineProps<{
        /** light – ant tamsaus ar mėlyno fono (antraštė, poraštė, nuotrauka) */
        tone?: 'default' | 'light';
        size?: 'md' | 'lg';
        class?: string;
    }>(),
    { tone: 'default', size: 'md', class: undefined },
);

const name = computed(() => String(usePage().props.name ?? ''));
</script>

<template>
    <span :class="cn('inline-flex items-center gap-2.5', props.class)">
        <span
            class="flex shrink-0 items-center justify-center rounded-xl bg-cta text-cta-foreground"
            :class="size === 'lg' ? 'size-11' : 'size-10'"
        >
            <AppLogoIcon
                :class="size === 'lg' ? 'size-6' : 'size-[1.375rem]'"
            />
        </span>
        <span
            class="font-display leading-none font-extrabold tracking-[-0.02em]"
            :class="[
                size === 'lg' ? 'text-[1.375rem]' : 'text-xl',
                tone === 'light' ? 'text-white' : 'text-foreground',
            ]"
        >
            {{ name }}
        </span>
    </span>
</template>
