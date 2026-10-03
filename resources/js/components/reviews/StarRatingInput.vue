<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed, ref } from 'vue';

// Žvaigždučių pasirinkimas 1–5. Viduje – tikri radio mygtukai: veikia klaviatūra (rodyklės) ir ekrano skaitytuvai
const model = defineModel<number | null>({ default: null });
defineProps<{ invalid?: boolean; name?: string }>();

const LABELS = ['Labai blogai', 'Blogai', 'Vidutiniškai', 'Gerai', 'Puikiai'];

const hovered = ref<number | null>(null);
const shown = computed(() => hovered.value ?? model.value ?? 0);
</script>

<template>
    <fieldset class="space-y-1">
        <legend class="sr-only">Įvertinimas</legend>
        <div
            class="flex items-center gap-1"
            :class="{ 'rounded-md ring-2 ring-destructive/40': invalid }"
            @mouseleave="hovered = null"
        >
            <label
                v-for="value in 5"
                :key="value"
                class="cursor-pointer rounded p-0.5 has-focus-visible:ring-2 has-focus-visible:ring-ring"
                :title="LABELS[value - 1]"
                @mouseenter="hovered = value"
            >
                <input
                    v-model="model"
                    type="radio"
                    class="sr-only"
                    :name="name ?? 'rating'"
                    :value="value"
                    :aria-label="`${value} iš 5 – ${LABELS[value - 1]}`"
                />
                <Star
                    class="size-7 transition-colors"
                    :class="
                        value <= shown
                            ? 'fill-amber-400 text-amber-400'
                            : 'text-muted-foreground/40'
                    "
                    aria-hidden="true"
                />
            </label>
            <span class="ml-2 text-sm text-muted-foreground">
                {{ shown ? LABELS[shown - 1] : 'Pasirinkite' }}
            </span>
        </div>
    </fieldset>
</template>
