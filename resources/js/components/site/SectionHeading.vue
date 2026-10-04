<script setup lang="ts">
import { cn } from '@/lib/utils';

// Skilties antraštė: mažas „eyebrow" užrašas, antraštė, aprašymas ir (nebūtinai) nuoroda dešinėje (slot „action").
// id – aria-labelledby skilčiai (<section aria-labelledby="…">).
const props = withDefaults(
    defineProps<{
        id?: string;
        eyebrow?: string;
        title: string;
        description?: string;
        tone?: 'default' | 'light';
        class?: string;
    }>(),
    {
        id: undefined,
        eyebrow: undefined,
        description: undefined,
        tone: 'default',
        class: undefined,
    },
);
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col gap-4 md:flex-row md:items-end md:justify-between',
                props.class,
            )
        "
    >
        <div class="max-w-2xl">
            <p
                v-if="eyebrow"
                class="mb-3 inline-flex items-center gap-2 text-xs font-semibold tracking-[0.14em] uppercase"
                :class="tone === 'light' ? 'text-cta' : 'text-primary'"
            >
                <span
                    class="h-px w-6"
                    :class="tone === 'light' ? 'bg-cta' : 'bg-primary'"
                    aria-hidden="true"
                />
                {{ eyebrow }}
            </p>
            <h2
                :id="id"
                class="text-3xl leading-[1.1] font-semibold text-balance md:text-4xl"
                :class="tone === 'light' ? 'text-white' : 'text-foreground'"
            >
                {{ title }}
            </h2>
            <p
                v-if="description"
                class="mt-3 text-base text-pretty md:text-lg"
                :class="
                    tone === 'light'
                        ? 'text-brand-deep-muted'
                        : 'text-muted-foreground'
                "
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.action" class="shrink-0">
            <slot name="action" />
        </div>
    </div>
</template>
