<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CategoryIcon from '@/components/catalog/CategoryIcon.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import PhotoSlot from '@/components/site/PhotoSlot.vue';
import { Button } from '@/components/ui/button';
import { toneFor } from '@/lib/brandTones';
import { home } from '@/routes';
import { index, show } from '@/routes/categories';
import type { CategoryTreeRoot, SeoMeta } from '@/types';

// Visų paslaugų katalogas: 1 lygis → 2 lygis → 3 lygis (medis iš cache).
// Etapas 10: kiekviena sritis – nuotrauka (arba spalvinis atsarginis dizainas) kairėje ir grupės dešinėje.
defineProps<{
    categories: CategoryTreeRoot[];
    seo: SeoMeta;
}>();

const createRequestUrl = '/uzklausos/nauja';
</script>

<template>
    <SeoHead :seo="seo" />

    <section
        class="border-b bg-gradient-to-b from-secondary/70 to-background dark:from-secondary/30"
    >
        <div class="page-container pt-6 pb-10 md:pt-8 md:pb-12">
            <Breadcrumbs
                :breadcrumbs="[
                    { title: 'Pradžia', href: home() },
                    { title: 'Paslaugos', href: index() },
                ]"
            />
            <div
                class="mt-6 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-4xl leading-[1.08] font-bold md:text-5xl">
                        Visos paslaugos
                    </h1>
                    <p class="mt-4 text-lg text-muted-foreground">
                        Išsirinkite paslaugą – pamatysite ją teikiančius
                        meistrus, jų kainas ir atsiliepimus.
                    </p>
                </div>
                <Button
                    variant="cta"
                    size="xl"
                    class="self-start lg:self-auto"
                    as-child
                >
                    <a :href="createRequestUrl">Aprašyti darbą</a>
                </Button>
            </div>

            <!-- Turinys: greitos nuorodos į 1 lygio kategorijas -->
            <nav aria-label="Paslaugų sritys" class="mt-8">
                <ul class="flex flex-wrap gap-2">
                    <li v-for="root in categories" :key="root.id">
                        <a
                            :href="`#${root.slug}`"
                            class="inline-flex items-center gap-2 rounded-full border bg-card px-3.5 py-1.5 text-sm shadow-xs transition-colors hover:border-primary/40 hover:text-primary"
                        >
                            <CategoryIcon
                                :name="root.icon"
                                class="size-4 text-primary"
                            />
                            {{ root.name }}
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </section>

    <div class="page-container space-y-6 pt-10 pb-20">
        <section
            v-for="root in categories"
            :id="root.slug"
            :key="root.id"
            class="grid scroll-mt-24 overflow-hidden rounded-3xl border bg-card shadow-soft lg:grid-cols-[19rem_minmax(0,1fr)]"
            :aria-labelledby="`${root.slug}-title`"
        >
            <Link
                :href="show(root.slug)"
                class="group relative isolate flex min-h-40 flex-col justify-end overflow-hidden p-6 text-white lg:min-h-full"
            >
                <PhotoSlot
                    :src="root.image_url"
                    alt=""
                    :tone="toneFor(root.icon, root.slug)"
                    :icon="root.icon"
                    img-class="-z-10"
                    sizes="(min-width: 1024px) 19rem, 100vw"
                />
                <div
                    class="absolute inset-0 -z-0 bg-gradient-to-t"
                    :class="
                        root.image_url
                            ? 'from-black/75 via-black/30 to-transparent'
                            : 'from-black/30 to-transparent'
                    "
                    aria-hidden="true"
                />
                <span class="relative">
                    <span
                        class="mb-3 flex size-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur-sm"
                    >
                        <CategoryIcon :name="root.icon" class="size-5" />
                    </span>
                    <h2
                        :id="`${root.slug}-title`"
                        class="text-2xl leading-tight font-semibold"
                    >
                        {{ root.name }}
                    </h2>
                    <span
                        class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-white/85 group-hover:text-white"
                    >
                        Visi srities meistrai
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </span>
                </span>
            </Link>

            <div
                class="grid gap-x-8 gap-y-6 p-6 sm:grid-cols-2 lg:p-8 xl:grid-cols-3"
            >
                <div v-for="group in root.children" :key="group.id">
                    <h3 class="font-semibold">
                        <Link
                            :href="show(group.slug)"
                            class="hover:text-primary hover:underline"
                            >{{ group.name }}</Link
                        >
                    </h3>
                    <ul class="mt-2 space-y-1.5 text-sm text-muted-foreground">
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
</template>
