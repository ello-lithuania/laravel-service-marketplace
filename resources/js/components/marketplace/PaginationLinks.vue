<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

// Paprastas „Ankstesnis / Kitas" puslapiavimas iš Laravel paginate() duomenų
defineProps<{
    paginator: Pick<Paginated<unknown>, 'links' | 'meta'>;
}>();
</script>

<template>
    <nav
        v-if="paginator.meta.last_page > 1"
        class="flex items-center justify-between gap-4 pt-4"
        aria-label="Puslapiai"
    >
        <p class="text-sm text-muted-foreground">
            {{ paginator.meta.from }}–{{ paginator.meta.to }} iš
            {{ paginator.meta.total }}
        </p>
        <div class="flex gap-2">
            <Button
                v-if="paginator.links.prev"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="paginator.links.prev" preserve-scroll>
                    <ChevronLeft class="size-4" /> Ankstesnis
                </Link>
            </Button>
            <Button
                v-if="paginator.links.next"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="paginator.links.next" preserve-scroll>
                    Kitas <ChevronRight class="size-4" />
                </Link>
            </Button>
        </div>
    </nav>
</template>
