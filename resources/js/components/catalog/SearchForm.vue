<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import NativeSelect from '@/components/catalog/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { search } from '@/routes';
import type { CityOption } from '@/types';

// Paieška: tekstas + miestas → /paieska?q=…&miestas=…
const props = defineProps<{
    cities: CityOption[];
    query?: string | null;
    city?: string | null;
}>();

const text = ref(props.query ?? '');
const city = ref(props.city ?? '');

function submit(): void {
    router.get(
        search.url({
            query: {
                q: text.value.trim() || null,
                miestas: city.value || null,
            },
        }),
    );
}
</script>

<template>
    <form
        role="search"
        class="flex flex-col gap-2 rounded-lg border bg-background p-2 shadow-sm sm:flex-row"
        @submit.prevent="submit"
    >
        <div class="relative flex-1">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="text"
                type="search"
                name="q"
                maxlength="100"
                class="h-10 pl-9"
                placeholder="Ko ieškote? Pvz. plytelių klijavimas"
                aria-label="Paslauga ar meistras"
            />
        </div>
        <NativeSelect
            v-model="city"
            class="sm:w-48 [&_select]:h-10"
            aria-label="Miestas ar rajonas"
        >
            <option value="">Visa Lietuva</option>
            <option
                v-for="option in cities"
                :key="option.slug"
                :value="option.slug"
            >
                {{ option.name }}
            </option>
        </NativeSelect>
        <Button type="submit" class="h-10">Ieškoti</Button>
    </form>
</template>
