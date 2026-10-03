<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { SeoMeta } from '@/types';

// SEO žymos puslapio <head>. head-key → data-inertia: tie patys raktai kaip app.blade.php,
// todėl serverio išvestos žymos pakeičiamos, o ne dubliuojamos.
defineProps<{ seo: SeoMeta }>();
</script>

<template>
    <Head :title="seo.title">
        <meta
            head-key="description"
            name="description"
            :content="seo.description"
        />
        <meta head-key="robots" name="robots" :content="seo.robots" />
        <link head-key="canonical" rel="canonical" :href="seo.canonical" />
        <meta head-key="og:title" property="og:title" :content="seo.title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="seo.description"
        />
        <meta head-key="og:url" property="og:url" :content="seo.canonical" />
    </Head>
</template>
