<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Hammer, Search } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

const props = defineProps<{
    passwordRules: string;
    /** Iš anksto pažymėta rolė (/register?role=provider) */
    role: 'client' | 'provider';
}>();

defineOptions({
    layout: {
        title: 'Susikurkite paskyrą',
        description: 'Užtruks mažiau nei minutę',
    },
});

// Rolė – paprasti radio mygtukai su name="role": <Form> pats surenka jų reikšmę
const selectedRole = ref(props.role);

const roles = [
    {
        value: 'client',
        title: 'Ieškau paslaugų',
        text: 'Aprašysiu darbą ir gausiu pasiūlymus',
        icon: Search,
    },
    {
        value: 'provider',
        title: 'Teikiu paslaugas',
        text: 'Gausiu užklausas iš klientų',
        icon: Hammer,
    },
] as const;
</script>

<template>
    <Head title="Registracija" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    Kaip naudosite platformą?
                </legend>
                <div class="grid grid-cols-2 gap-3">
                    <label
                        v-for="option in roles"
                        :key="option.value"
                        class="flex cursor-pointer flex-col gap-1 rounded-lg border p-3 text-sm transition-colors has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                        :class="
                            selectedRole === option.value
                                ? 'border-primary bg-primary/5'
                                : 'hover:bg-accent'
                        "
                        :data-test="`role-${option.value}`"
                    >
                        <input
                            v-model="selectedRole"
                            type="radio"
                            name="role"
                            :value="option.value"
                            class="sr-only"
                        />
                        <component
                            :is="option.icon"
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span class="font-medium">{{ option.title }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            option.text
                        }}</span>
                    </label>
                </div>
                <InputError :message="errors.role" />
            </fieldset>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="first_name">Vardas</Label>
                    <Input
                        id="first_name"
                        type="text"
                        required
                        v-focus
                        :tabindex="1"
                        autocomplete="given-name"
                        name="first_name"
                        placeholder="Jonas"
                    />
                    <InputError :message="errors.first_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="last_name">Pavardė</Label>
                    <Input
                        id="last_name"
                        type="text"
                        required
                        :tabindex="1"
                        autocomplete="family-name"
                        name="last_name"
                        placeholder="Petraitis"
                    />
                    <InputError :message="errors.last_name" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="email">El. pašto adresas</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="vardas@pavyzdys.lt"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Slaptažodis</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Slaptažodis"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation"
                    >Pakartokite slaptažodį</Label
                >
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Pakartokite slaptažodį"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Sukurti paskyrą
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Jau turite paskyrą?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >Prisijunkite</TextLink
            >
        </div>
    </Form>
</template>
