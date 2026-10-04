<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import CategoryIcon from '@/components/catalog/CategoryIcon.vue';
import type { BrandTone } from '@/lib/brandTones';
import { brandTones } from '@/lib/brandTones';

// Atsarginis dizainas vietoj nuotraukos (Etapas 10): tono gradientas, „topografinės" linijos ir didelė ikona.
// Nuotraukos bus tik administratoriui jas atsisiuntus, todėl šis vaizdas turi atrodyti kaip sąmoningas dizainas,
// o ne kaip „sugedęs paveikslėlis". Tik CSS ir SVG – jokių papildomų failų.
const props = withDefaults(
    defineProps<{
        tone?: BrandTone;
        /** lucide ikonos vardas iš categories.icon */
        icon?: string | null;
        /** arba bet kuris komponentas (pvz. lucide ikona) */
        iconComponent?: Component | null;
        /** ikona: didelė kampe (cover) arba nedidelė centre (center) */
        iconPlacement?: 'corner' | 'center' | 'none';
    }>(),
    {
        tone: () => brandTones.pine,
        icon: null,
        iconComponent: null,
        iconPlacement: 'corner',
    },
);

const background = computed(
    () =>
        `radial-gradient(120% 90% at 0% 0%, rgb(255 255 255 / 0.14), transparent 55%), linear-gradient(135deg, ${props.tone.from}, ${props.tone.to})`,
);
</script>

<template>
    <div
        class="absolute inset-0 overflow-hidden text-white"
        :style="{ backgroundImage: background }"
        aria-hidden="true"
    >
        <!-- Topografinės linijos: preserveAspectRatio slice – užpildo bet kokio formato plotą -->
        <svg
            class="absolute inset-0 size-full opacity-[0.13]"
            viewBox="0 0 400 300"
            preserveAspectRatio="xMidYMid slice"
            fill="none"
            stroke="currentColor"
            stroke-width="1.2"
        >
            <path d="M-20 230c60-30 110-20 160 5s110 30 170-10 90-40 120-30" />
            <path d="M-20 200c55-28 105-22 155 2s115 28 175-14 85-38 115-28" />
            <path d="M-20 170c50-26 100-24 150 0s120 26 180-18 80-36 110-26" />
            <path d="M-20 140c45-24 95-26 145-2s125 24 185-22 75-34 105-24" />
            <path d="M-20 110c40-22 90-28 140-4s130 22 190-26 70-32 100-22" />
            <path d="M-20 80c35-20 85-30 135-6s135 20 195-30 65-30 95-20" />
            <path d="M-20 50c30-18 80-32 130-8s140 18 200-34 60-28 90-18" />
        </svg>

        <template v-if="iconPlacement === 'corner'">
            <component
                :is="iconComponent"
                v-if="iconComponent"
                class="absolute -right-[6%] -bottom-[14%] size-[62%] max-h-[85%] opacity-25 transition-transform duration-700 group-hover:scale-105 group-hover:-rotate-3"
                :stroke-width="1.25"
            />
            <CategoryIcon
                v-else
                :name="icon"
                class="absolute -right-[6%] -bottom-[14%] size-[62%] max-h-[85%] opacity-25 transition-transform duration-700 group-hover:scale-105 group-hover:-rotate-3"
                :stroke-width="1.25"
            />
        </template>
        <div
            v-else-if="iconPlacement === 'center'"
            class="absolute inset-0 flex items-center justify-center"
        >
            <span
                class="flex size-16 items-center justify-center rounded-2xl bg-white/12 ring-1 ring-white/20 backdrop-blur-sm"
            >
                <component
                    :is="iconComponent"
                    v-if="iconComponent"
                    class="size-8"
                />
                <CategoryIcon v-else :name="icon" class="size-8" />
            </span>
        </div>
    </div>
</template>
