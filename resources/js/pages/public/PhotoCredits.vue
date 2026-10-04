<script setup lang="ts">
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import PhotoCreditCard from '@/components/site/PhotoCreditCard.vue';
import { home, photoCredits } from '@/routes';
import type { Paginated, SeoMeta } from '@/types';
import type { PhotoCreditItem } from '@/types/site';

// Nuotraukų autoriai ir licencijos (Etapas 10). CC BY ir CC BY-SA licencijos reikalauja nurodyti autorių,
// todėl rodom visas svetainėje naudojamas svetimas nuotraukas. Svetainės ir kategorijų – pirmame puslapyje,
// demo rinkinys – puslapiais (?puslapis=2).
const props = defineProps<{
    sitePhotos: PhotoCreditItem[];
    categories: PhotoCreditItem[];
    demo: Paginated<PhotoCreditItem | null>;
    seo: SeoMeta;
}>();

const demoItems = computed(() =>
    props.demo.data.filter((item): item is PhotoCreditItem => item !== null),
);

const sections = computed(() =>
    [
        {
            id: 'svetaine',
            title: 'Svetainės nuotraukos',
            description: null,
            items: props.sitePhotos,
        },
        {
            id: 'kategorijos',
            title: 'Paslaugų kategorijos',
            description: null,
            items: props.categories,
        },
        {
            id: 'demo',
            title: 'Demonstraciniai duomenys',
            description:
                'Pavyzdinių teikėjų darbų ir viršelių nuotraukos (rodomos tik bandomojoje svetainės versijoje).',
            items: demoItems.value,
        },
    ].filter((section) => section.items.length > 0),
);
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto max-w-6xl px-4 py-8 md:py-12">
        <Breadcrumbs
            :breadcrumbs="[
                { title: 'Pradžia', href: home() },
                { title: 'Nuotraukų autoriai', href: photoCredits() },
            ]"
        />
        <h1 class="mt-4 text-3xl font-semibold tracking-tight">
            Nuotraukų autoriai
        </h1>
        <p class="mt-2 max-w-2xl text-muted-foreground">
            Svetainėje naudojame nemokamas nuotraukas iš
            <a
                href="https://www.pexels.com"
                target="_blank"
                rel="noopener"
                class="underline underline-offset-2"
                >Pexels</a
            >
            ir
            <a
                href="https://openverse.org"
                target="_blank"
                rel="noopener"
                class="underline underline-offset-2"
                >Openverse</a
            >
            (Creative Commons licencijos). Dėkojame autoriams! Žemiau – kas
            nufotografavo, kur nuotrauka paskelbta ir pagal kokią licenciją ją
            naudojame.
        </p>

        <p
            v-if="sections.length === 0"
            class="mt-10 rounded-lg border border-dashed p-8 text-center text-muted-foreground"
        >
            Šiuo metu svetainėje naudojamos tik mūsų pačių nuotraukos.
        </p>

        <section
            v-for="section in sections"
            :key="section.id"
            :aria-labelledby="`nuotraukos-${section.id}`"
            class="mt-10"
        >
            <h2 :id="`nuotraukos-${section.id}`" class="text-xl font-semibold">
                {{ section.title }}
            </h2>
            <p
                v-if="section.description"
                class="mt-1 text-sm text-muted-foreground"
            >
                {{ section.description }}
            </p>
            <ul class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="item in section.items" :key="item.id">
                    <PhotoCreditCard :item="item" />
                </li>
            </ul>
        </section>

        <CatalogPagination :paginated="demo" class="mt-8" />
    </div>
</template>
