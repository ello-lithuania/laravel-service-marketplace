<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { ChevronDown } from "@lucide/vue"
import { cn } from "@/lib/utils"

// Naršyklės <select> su tuo pačiu stiliumi kaip Input. Palaiko <optgroup> (pvz. miestai pagal apskritis)
defineOptions({ inheritAttrs: false })

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const model = defineModel<string | number | null>()
</script>

<template>
  <div class="relative">
    <select
      v-model="model"
      v-bind="$attrs"
      data-slot="native-select"
      :class="cn(
        'border-input dark:bg-input/30 h-9 w-full min-w-0 appearance-none rounded-md border bg-card py-1 pr-8 pl-3 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
        'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
        props.class,
      )"
    >
      <slot />
    </select>
    <ChevronDown class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 opacity-50" />
  </div>
</template>
