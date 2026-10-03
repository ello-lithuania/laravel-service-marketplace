<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PortfolioForm from '@/components/account/PortfolioForm.vue';
import Heading from '@/components/Heading.vue';
import { index, update } from '@/routes/portfolio';
import type { IdName, PortfolioItemData, RegionOption } from '@/types';

defineProps<{
    item: PortfolioItemData;
    categories: IdName[];
    regions: RegionOption[];
    maxImages: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Atlikti darbai', href: index() }],
    },
});
</script>

<template>
    <Head :title="`Redaguoti: ${item.title}`" />

    <div class="mx-auto w-full max-w-3xl px-4 py-6">
        <Heading title="Darbo redagavimas" :description="item.title" />

        <PortfolioForm
            :item="item"
            :categories="categories"
            :regions="regions"
            :max-images="maxImages"
            :action="update(item.id).url"
        />
    </div>
</template>
