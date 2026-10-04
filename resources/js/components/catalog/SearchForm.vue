<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown, MapPin, Search } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { search } from '@/routes';
import type { CityOption } from '@/types';

// Paieška: tekstas + miestas → /paieska?q=…&miestas=…
// Etapas 10: viena „sujungta" juosta (kaip didelėse platformose) – laukai atskirti linija, o ne atskiri rėmeliai.
const props = withDefaults(
    defineProps<{
        cities: CityOption[];
        query?: string | null;
        city?: string | null;
        /** hero – didesnė, pradžios puslapio viršuje */
        size?: 'default' | 'hero';
    }>(),
    { query: null, city: null, size: 'default' },
);

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
        class="flex flex-col gap-1.5 border border-border/80 bg-card p-1.5 sm:flex-row sm:items-center sm:gap-0"
        :class="
            size === 'hero'
                ? 'rounded-2xl shadow-lift'
                : 'rounded-xl shadow-soft'
        "
        @submit.prevent="submit"
    >
        <label
            class="relative flex flex-1 items-center border-b border-border/70 sm:border-b-0"
        >
            <span class="sr-only">Paslauga ar meistras</span>
            <Search
                class="pointer-events-none absolute left-3.5 size-5 text-primary"
                aria-hidden="true"
            />
            <input
                v-model="text"
                type="search"
                name="q"
                maxlength="100"
                class="w-full rounded-xl bg-transparent pr-3 pl-11 outline-none placeholder:text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/30"
                :class="
                    size === 'hero' ? 'h-13 text-base' : 'h-11 text-[0.9375rem]'
                "
                placeholder="Pvz. plytelių klijavimas"
            />
        </label>

        <span
            class="hidden h-7 w-px shrink-0 bg-border sm:block"
            aria-hidden="true"
        />

        <label class="relative flex items-center sm:w-56">
            <span class="sr-only">Miestas ar rajonas</span>
            <MapPin
                class="pointer-events-none absolute left-3.5 size-5 text-muted-foreground"
                aria-hidden="true"
            />
            <!-- Paprastas <select>: telefone atsidaro sistemos sąrašas, galima „šokti" spaudžiant raidę -->
            <select
                v-model="city"
                class="w-full appearance-none rounded-xl bg-transparent pr-9 pl-11 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/30"
                :class="
                    size === 'hero' ? 'h-13 text-base' : 'h-11 text-[0.9375rem]'
                "
            >
                <option value="">Visa Lietuva</option>
                <option
                    v-for="option in cities"
                    :key="option.slug"
                    :value="option.slug"
                >
                    {{ option.name }}
                </option>
            </select>
            <ChevronDown
                class="pointer-events-none absolute right-3 size-4 text-muted-foreground"
                aria-hidden="true"
            />
        </label>

        <Button
            type="submit"
            class="rounded-xl"
            :class="size === 'hero' ? 'h-13 px-7 text-base' : 'h-11 px-5'"
        >
            <Search class="size-4 sm:hidden" aria-hidden="true" />
            Ieškoti
        </Button>
    </form>
</template>
