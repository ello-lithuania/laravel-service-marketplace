<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import { cn } from '@/lib/utils';
import { search } from '@/routes';

// Greita paieška antraštėje: tik tekstas → /paieska?q=… (tuščia paieška serveryje nukreipia į /meistrai)
// Etapas 11: tone="brand" – permatomas laukas mėlynoje antraštėje
const props = withDefaults(
    defineProps<{
        class?: string;
        inputId?: string;
        autofocus?: boolean;
        tone?: 'default' | 'brand';
    }>(),
    { class: undefined, inputId: undefined, autofocus: false, tone: 'default' },
);

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
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
            :class="
                tone === 'brand' ? 'text-brand-muted' : 'text-muted-foreground'
            "
            aria-hidden="true"
        />
        <input
            :id="inputId ?? 'header-search'"
            v-model="text"
            v-focus="autofocus"
            type="search"
            name="q"
            maxlength="100"
            placeholder="Ieškoti paslaugos…"
            class="h-10 w-full rounded-full border pr-3 pl-9 text-sm transition-[color,box-shadow] outline-none focus-visible:ring-[3px]"
            :class="
                tone === 'brand'
                    ? 'border-white/20 bg-white/10 text-white placeholder:text-brand-muted focus-visible:border-white/50 focus-visible:ring-white/30'
                    : 'border-input bg-card shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/40 dark:bg-input/30'
            "
        />
    </form>
</template>
