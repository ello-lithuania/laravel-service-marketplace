<script setup lang="ts">
import {
    Car,
    GraduationCap,
    Hammer,
    Laptop,
    LayoutGrid,
    PartyPopper,
    Plug,
    Scissors,
    Sofa,
    Sparkles,
    Trees,
    Truck,
    Wrench,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';

// categories.icon saugo lucide ikonos vardą ('party-popper'). Importuojam tik naudojamas ikonas –
// visų ~1500 importas (import * as icons) labai padidintų JavaScript failą.
// Nežinomas vardas (pvz. įvestas per Filament) → bendra ikona.
const icons: Record<string, Component> = {
    hammer: Hammer,
    wrench: Wrench,
    plug: Plug,
    sparkles: Sparkles,
    truck: Truck,
    trees: Trees,
    sofa: Sofa,
    car: Car,
    scissors: Scissors,
    laptop: Laptop,
    'party-popper': PartyPopper,
    'graduation-cap': GraduationCap,
};

const props = defineProps<{ name: string | null }>();

const icon = computed(() => (props.name && icons[props.name]) || LayoutGrid);
</script>

<template>
    <component :is="icon" aria-hidden="true" />
</template>
