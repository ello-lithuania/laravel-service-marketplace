<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ImageOff,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { create, destroy, edit, index, reorder } from '@/routes/portfolio';
import type { PortfolioListItem } from '@/types';

const props = defineProps<{
    items: PortfolioListItem[];
    maxImages: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Atlikti darbai', href: index() }],
    },
});

// Perkėlimas aukštyn / žemyn: sukeičiam vietomis ir siunčiam visą naują tvarką
function move(position: number, direction: -1 | 1): void {
    const ids = props.items.map((item) => item.id);
    const target = position + direction;

    [ids[position], ids[target]] = [ids[target], ids[position]];

    router.put(reorder().url, { ids }, { preserveScroll: true });
}

function remove(item: PortfolioListItem): void {
    router.delete(destroy(item.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Atlikti darbai" />

    <div class="mx-auto w-full max-w-5xl px-4 py-6">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Atlikti darbai"
                description="Parodykite klientams, ką jau esate padarę. Darbai rodomi jūsų viešame profilyje nurodyta tvarka."
            />
            <Button as-child>
                <Link :href="create()"
                    ><Plus class="size-4" /> Pridėti darbą</Link
                >
            </Button>
        </div>

        <div
            v-if="items.length === 0"
            class="rounded-lg border border-dashed p-10 text-center text-muted-foreground"
        >
            Dar neturite pridėtų darbų.
        </div>

        <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li
                v-for="(item, position) in items"
                :key="item.id"
                class="flex flex-col overflow-hidden rounded-lg border"
            >
                <div class="aspect-[4/3] bg-muted">
                    <img
                        v-if="item.cover"
                        :src="item.cover"
                        :alt="item.title"
                        class="size-full object-cover"
                    />
                    <div
                        v-else
                        class="flex size-full items-center justify-center text-muted-foreground"
                    >
                        <ImageOff class="size-8" />
                    </div>
                </div>
                <div class="flex flex-1 flex-col gap-1 p-3">
                    <p class="font-medium">{{ item.title }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            [item.category, item.city, item.completed_date]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Nuotraukų: {{ item.images_count }} iš {{ maxImages }}
                    </p>
                </div>
                <div class="flex items-center gap-1 border-t p-2">
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="edit(item.id)"
                            ><Pencil class="size-4" /> Redaguoti</Link
                        >
                    </Button>

                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="ghost" size="sm">
                                <Trash2 class="size-4" /> Ištrinti
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Ištrinti darbą?</DialogTitle>
                                <DialogDescription>
                                    „{{ item.title }}“ ir visos jo nuotraukos
                                    bus ištrinti negrįžtamai.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary"
                                        >Atšaukti</Button
                                    >
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    @click="remove(item)"
                                >
                                    Ištrinti
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <div class="ml-auto flex">
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="position === 0"
                            aria-label="Perkelti aukščiau"
                            @click="move(position, -1)"
                        >
                            <ArrowUp class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="position === items.length - 1"
                            aria-label="Perkelti žemiau"
                            @click="move(position, 1)"
                        >
                            <ArrowDown class="size-4" />
                        </Button>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
