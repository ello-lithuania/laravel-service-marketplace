<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProviderWizard from '@/components/account/ProviderWizard.vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import { wizard as wizardRoute } from '@/routes/provider';
import { destroy, update } from '@/routes/provider/images';
import type { WizardStep } from '@/types';

defineProps<{
    wizard: WizardStep[];
    logo: string | null;
    cover: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Teikėjo profilis', href: wizardRoute() }],
    },
});
</script>

<template>
    <Head title="Teikėjo profilis – logotipas ir viršelis" />

    <ProviderWizard
        :steps="wizard"
        :current="null"
        title="Logotipas ir viršelis"
        description="Neprivaloma, bet profilis su nuotraukomis atrodo patikimiau."
    >
        <div class="space-y-8">
            <ImageUploadField
                label="Logotipas"
                description="Rodomas paieškos rezultatuose ir profilyje. Tinka kvadratinis paveikslėlis."
                :url="logo"
                :upload-url="update('logotipas').url"
                :delete-url="destroy('logotipas').url"
            />

            <ImageUploadField
                label="Viršelio nuotrauka"
                description="Plati nuotrauka profilio viršuje (bus apkirpta iki 1200×400)."
                :url="cover"
                :upload-url="update('virselis').url"
                :delete-url="destroy('virselis').url"
                shape="wide"
            />
        </div>
    </ProviderWizard>
</template>
