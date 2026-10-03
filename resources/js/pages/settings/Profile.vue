<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import ImageUploadField from '@/components/ImageUploadField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyAvatar,
    update as updateAvatar,
} from '@/routes/avatar';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profilis',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profilio nustatymai" />

    <h1 class="sr-only">Profilio nustatymai</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profilis"
            description="Jūsų vardas, pavardė ir el. pašto adresas"
        />

        <ImageUploadField
            v-if="user.email_verified_at"
            label="Profilio nuotrauka"
            description="Rodoma šalia jūsų vardo žinutėse ir atsiliepimuose"
            :url="user.avatar"
            :upload-url="updateAvatar().url"
            :delete-url="destroyAvatar().url"
            shape="round"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="first_name">Vardas</Label>
                    <Input
                        id="first_name"
                        class="mt-1 block w-full"
                        name="first_name"
                        :default-value="user.first_name"
                        required
                        autocomplete="given-name"
                    />
                    <InputError class="mt-2" :message="errors.first_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="last_name">Pavardė</Label>
                    <Input
                        id="last_name"
                        class="mt-1 block w-full"
                        name="last_name"
                        :default-value="user.last_name"
                        required
                        autocomplete="family-name"
                    />
                    <InputError class="mt-2" :message="errors.last_name" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="email">El. pašto adresas</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="El. pašto adresas"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Jūsų el. pašto adresas nepatvirtintas.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Siųsti patvirtinimo laišką dar kartą.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Nauja patvirtinimo nuoroda išsiųsta jūsų el. pašto adresu.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Išsaugoti</Button
                >
            </div>
        </Form>
    </div>

    <!-- Etapas 8: paskyros ištrynimas perkeltas į Nustatymai → Privatumas (BDAR) -->
</template>
