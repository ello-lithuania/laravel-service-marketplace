<script setup lang="ts">
// Etapas 10: padalintas išdėstymas su nuotrauka (AuthSplitLayout), ne paprastas centruotas
import AuthLayout from '@/layouts/auth/AuthSplitLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <slot />
    </AuthLayout>
</template>
