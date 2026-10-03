<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Išvaizda',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Išvaizda" />

    <h1 class="sr-only">Išvaizdos nustatymai</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Išvaizda"
            description="Šviesi, tamsi arba pagal jūsų įrenginio nustatymus"
        />
        <AppearanceTabs />
    </div>
</template>
