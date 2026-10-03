<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PortfolioForm from '@/components/account/PortfolioForm.vue';
import Heading from '@/components/Heading.vue';
import { create, index, store } from '@/routes/portfolio';
import type { IdName, RegionOption } from '@/types';

defineProps<{
    categories: IdName[];
    regions: RegionOption[];
    maxImages: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Atlikti darbai', href: index() },
            { title: 'Naujas darbas', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Naujas darbas" />

    <div class="mx-auto w-full max-w-3xl px-4 py-6">
        <Heading
            title="Naujas darbas"
            description="Trumpai aprašykite darbą ir įkelkite bent vieną nuotrauką."
        />

        <PortfolioForm
            :item="null"
            :categories="categories"
            :regions="regions"
            :max-images="maxImages"
            :action="store().url"
        />
    </div>
</template>
