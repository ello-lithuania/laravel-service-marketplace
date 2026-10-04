<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import { cn } from '@/lib/utils';
import { search } from '@/routes';

// Greita paieška antraštėje: tik tekstas → /paieska?q=… (tuščia paieška serveryje nukreipia į /meistrai)
const props = defineProps<{ class?: string; inputId?: string }>();

const emit = defineEmits<{ submitted: [] }>();

const text = ref('');

function submit(): void {
    router.get(search.url({ query: { q: text.value.trim() || null } }));
    emit('submitted');
}
</script>

<template>
    <form
        role="search"
        :class="cn('relative', props.class)"
        @submit.prevent="submit"
    >
        <label :for="inputId ?? 'header-search'" class="sr-only"
            >Paslauga ar meistras</label
        >
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
        <input
            :id="inputId ?? 'header-search'"
            v-model="text"
            type="search"
            name="q"
            maxlength="100"
            placeholder="Ieškoti paslaugos…"
            class="h-9 w-full rounded-full border border-input bg-card pr-3 pl-9 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 dark:bg-input/30"
        />
    </form>
</template>
