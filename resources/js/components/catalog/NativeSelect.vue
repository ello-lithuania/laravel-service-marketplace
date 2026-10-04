<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { cn } from '@/lib/utils';

// Paprastas <select>: telefone atsidaro sistemos pasirinkimo langas, o 60 savivaldybių sąraše
// galima „šokti" spaudžiant raidę. Stilius – kaip ui/input.
defineOptions({ inheritAttrs: false });
defineProps<{ class?: string }>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <div :class="cn('relative', $props.class)">
        <select
            v-model="model"
            class="h-9 w-full appearance-none rounded-md border border-input bg-card py-1 pr-8 pl-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            v-bind="$attrs"
        >
            <slot />
        </select>
        <ChevronDown
            class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
    </div>
</template>
