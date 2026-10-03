<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CategoryIcon from '@/components/catalog/CategoryIcon.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import { home } from '@/routes';
import { index, show } from '@/routes/categories';
import type { CategoryTreeRoot, SeoMeta } from '@/types';

// Visų paslaugų katalogas: 1 lygis → 2 lygis → 3 lygis (medis iš cache).
defineProps<{
    categories: CategoryTreeRoot[];
    seo: SeoMeta;
}>();
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto max-w-6xl px-4 py-8 md:py-12">
        <Breadcrumbs
            :breadcrumbs="[
                { title: 'Pradžia', href: home() },
                { title: 'Paslaugos', href: index() },
            ]"
        />
        <h1 class="mt-4 text-3xl font-semibold tracking-tight">
            Visos paslaugos
        </h1>
        <p class="mt-2 max-w-2xl text-muted-foreground">
            Išsirinkite paslaugą – pamatysite ją teikiančius meistrus, jų kainas
            ir atsiliepimus.
        </p>

        <!-- Turinys: greitos nuorodos į 1 lygio kategorijas -->
        <nav aria-label="Paslaugų sritys" class="mt-6 flex flex-wrap gap-2">
            <a
                v-for="root in categories"
                :key="root.id"
                :href="`#${root.slug}`"
                class="rounded-full border px-3 py-1 text-sm transition-colors hover:bg-accent"
                >{{ root.name }}</a
            >
        </nav>

        <div class="mt-10 space-y-12">
            <section
                v-for="root in categories"
                :id="root.slug"
                :key="root.id"
                class="scroll-mt-6"
            >
                <h2 class="flex items-center gap-3 text-xl font-semibold">
                    <CategoryIcon
                        :name="root.icon"
                        class="size-6 text-primary"
                    />
                    <Link :href="show(root.slug)" class="hover:underline">{{
                        root.name
                    }}</Link>
                </h2>
                <div class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="group in root.children" :key="group.id">
                        <h3 class="font-medium">
                            <Link
                                :href="show(group.slug)"
                                class="hover:underline"
                                >{{ group.name }}</Link
                            >
                        </h3>
                        <ul
                            class="mt-2 space-y-1 text-sm text-muted-foreground"
                        >
                            <li v-for="leaf in group.children" :key="leaf.id">
                                <Link
                                    :href="show(leaf.slug)"
                                    class="hover:text-foreground hover:underline"
                                    >{{ leaf.name }}</Link
                                >
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
