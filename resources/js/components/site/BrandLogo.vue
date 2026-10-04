<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { cn } from '@/lib/utils';

// Logotipas: ženklas + pavadinimas iš config('app.name') (galutinis vardas dar nepasirinktas).
// Pirmas žodis paryškintas, kiti – plonesni: tipografinis „wordmark", kuris tinka bet kokiam pavadinimui.
const props = withDefaults(
    defineProps<{
        /** light – ant tamsaus fono (poraštė, nuotrauka) */
        tone?: 'default' | 'light';
        size?: 'md' | 'lg';
        class?: string;
    }>(),
    { tone: 'default', size: 'md', class: undefined },
);

const name = computed(() => String(usePage().props.name ?? ''));

const parts = computed(() => {
    const [first, ...rest] = name.value.trim().split(/\s+/u);

    return { first: first ?? '', rest: rest.join(' ') };
});
</script>

<template>
    <span :class="cn('inline-flex items-center gap-2.5', props.class)">
        <span
            class="relative flex shrink-0 items-center justify-center rounded-[0.7rem] shadow-soft"
            :class="[
                size === 'lg' ? 'size-11' : 'size-9',
                tone === 'light'
                    ? 'bg-white text-primary dark:text-[#0f5b45]'
                    : 'bg-primary text-primary-foreground',
            ]"
        >
            <AppLogoIcon :class="size === 'lg' ? 'size-7' : 'size-6'" />
            <!-- Oranžinis taškas – „naujas pasiūlymas": prekės ženklo akcentas -->
            <span
                class="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-cta ring-2"
                :class="
                    tone === 'light' ? 'ring-brand-deep' : 'ring-background'
                "
            />
        </span>
        <span
            class="font-display leading-none tracking-tight"
            :class="[
                size === 'lg' ? 'text-xl' : 'text-[1.0625rem]',
                tone === 'light' ? 'text-white' : 'text-foreground',
            ]"
        >
            <span class="font-bold">{{ parts.first }}</span>
            <template v-if="parts.rest">
                {{ ' '
                }}<span
                    class="font-normal"
                    :class="
                        tone === 'light'
                            ? 'text-brand-deep-muted'
                            : 'text-muted-foreground'
                    "
                    >{{ parts.rest }}</span
                >
            </template>
        </span>
    </span>
</template>
