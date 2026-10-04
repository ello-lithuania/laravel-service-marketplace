<script setup lang="ts">
import { Plus } from '@lucide/vue';

// Dažni klausimai: natūralūs <details>/<summary> – veikia be JavaScript, klaviatūra ir ekrano skaitytuvai juos
// supranta patys (atidaryta/uždaryta). Alternatyva – ui/collapsible (reka-ui), bet čia jo nereikia.
defineProps<{ items: { q: string; a: string }[] }>();
</script>

<template>
    <div class="divide-y rounded-2xl border bg-card shadow-soft">
        <details
            v-for="(item, index) in items"
            :key="item.q"
            class="group px-5 sm:px-6"
            :open="index === 0"
        >
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-4 py-5 font-medium marker:hidden focus-visible:rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none [&::-webkit-details-marker]:hidden"
            >
                <span class="text-[1.0625rem]">{{ item.q }}</span>
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-secondary text-secondary-foreground transition-transform duration-300 group-open:rotate-45"
                    aria-hidden="true"
                >
                    <Plus class="size-4" />
                </span>
            </summary>
            <p class="-mt-1 pr-10 pb-5 leading-relaxed text-muted-foreground">
                {{ item.a }}
            </p>
        </details>
    </div>
</template>
