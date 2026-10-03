<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import FormTextarea from '@/components/marketplace/FormTextarea.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { cancel } from '@/routes/service-requests';
import type { ServiceRequestSummary } from '@/types';

const props = defineProps<{
    serviceRequest: Pick<ServiceRequestSummary, 'slug' | 'status'>;
}>();

const open = ref(false);
// Vykdomą užklausą galima atšaukti tik su priežastimi (docs/STATES.md 1 sk.)
const reasonRequired = props.serviceRequest.status.value === 'in_progress';

const form = useForm({ reason: '' });

function submit(): void {
    form.submit(cancel(props.serviceRequest), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" data-test="cancel-request-button">
                Atšaukti užklausą
            </Button>
        </DialogTrigger>
        <DialogContent>
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Atšaukti užklausą?</DialogTitle>
                    <DialogDescription v-if="reasonRequired">
                        Darbas jau vykdomas. Nurodykite priežastį – ją matys
                        teikėjas. Kreditai teikėjui negrąžinami.
                    </DialogDescription>
                    <DialogDescription v-else>
                        Užklausa bus uždaryta, o teikėjams, atsiuntusiems
                        pasiūlymus, grąžinsime kreditus.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="reason">
                        Priežastis{{ reasonRequired ? '' : ' (neprivaloma)' }}
                    </Label>
                    <FormTextarea
                        id="reason"
                        v-model="form.reason"
                        :rows="3"
                        :maxlength="500"
                        :invalid="!!form.errors.reason"
                        placeholder="Pvz. teikėjas neatvyko sutartu laiku"
                    />
                    <InputError :message="form.errors.reason" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Grįžti
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="form.processing"
                    >
                        Atšaukti užklausą
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
