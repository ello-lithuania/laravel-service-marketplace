<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Patvirtinkite el. paštą',
        description:
            'Išsiuntėme jums laišką su patvirtinimo nuoroda. Paspauskite ją ir galėsite tęsti.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="El. pašto patvirtinimas" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        Nauja patvirtinimo nuoroda išsiųsta registracijos metu nurodytu el.
        pašto adresu.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <p class="text-sm text-muted-foreground">
            Laiško nėra? Patikrinkite šlamšto (spam) aplanką arba atsiųskite
            nuorodą dar kartą.
        </p>

        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Siųsti dar kartą
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Atsijungti
        </TextLink>
    </Form>
</template>
