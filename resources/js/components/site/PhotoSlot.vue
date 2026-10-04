<script setup lang="ts">
import type { Component } from 'vue';
import { ref, watch } from 'vue';
import PhotoFallback from '@/components/site/PhotoFallback.vue';
import type { BrandTone } from '@/lib/brandTones';
import { cn } from '@/lib/utils';

// Nuotraukos vieta: yra URL – <img>, nėra (null) arba nepavyko užkrauti – atsarginis dizainas (PhotoFallback).
// Užpildo tėvą (absolute inset-0), o tėvas nustato formatą (aspect-[4/3]) – todėl puslapis „nešokinėja" kraunantis.
// width/height – tikri nuotraukos matmenys (konversijos dydis), naršyklė iš jų žino proporcijas dar prieš atsisiuntimą.
const props = withDefaults(
    defineProps<{
        src: string | null | undefined;
        alt: string;
        width?: number;
        height?: number;
        /** virš ekrano linijos (hero) – krauti iškart ir su aukštu prioritetu */
        eager?: boolean;
        tone?: BrandTone;
        icon?: string | null;
        iconComponent?: Component | null;
        iconPlacement?: 'corner' | 'center' | 'none';
        imgClass?: string;
        /** naršyklei: kokio pločio bus paveikslėlis (responsive images) */
        sizes?: string;
    }>(),
    {
        width: 800,
        height: 600,
        eager: false,
        tone: undefined,
        icon: null,
        iconComponent: null,
        iconPlacement: 'corner',
        imgClass: '',
        sizes: undefined,
    },
);

const failed = ref(false);

watch(
    () => props.src,
    () => {
        failed.value = false;
    },
);
</script>

<template>
    <img
        v-if="src && !failed"
        :src="src"
        :alt="alt"
        :width="width"
        :height="height"
        :sizes="sizes"
        :loading="eager ? 'eager' : 'lazy'"
        :fetchpriority="eager ? 'high' : 'auto'"
        decoding="async"
        :class="
            cn(
                'absolute inset-0 size-full object-cover transition-transform duration-700 group-hover:scale-[1.03]',
                imgClass,
            )
        "
        @error="failed = true"
    />
    <slot v-else name="fallback">
        <PhotoFallback
            :tone="tone"
            :icon="icon"
            :icon-component="iconComponent"
            :icon-placement="iconPlacement"
        />
    </slot>
</template>
