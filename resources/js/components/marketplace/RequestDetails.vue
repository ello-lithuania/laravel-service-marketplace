<script setup lang="ts">
import { CalendarClock, Coins, Lock, MapPin } from '@lucide/vue';
import { formatBudget, formatDate } from '@/lib/marketplace';
import type { ServiceRequestDetail } from '@/types';

// Užklausos aprašymas ir pagrindiniai duomenys – bendri kliento ir teikėjo puslapiams
defineProps<{
    serviceRequest: ServiceRequestDetail;
}>();
</script>

<template>
    <section class="space-y-4 rounded-xl border bg-card p-4 md:p-6">
        <p class="text-sm whitespace-pre-line">
            {{ serviceRequest.description }}
        </p>

        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div class="flex items-start gap-2">
                <MapPin class="mt-0.5 size-4 text-muted-foreground" />
                <div>
                    <dt class="text-muted-foreground">Vieta</dt>
                    <dd class="font-medium">{{ serviceRequest.city?.name }}</dd>
                </div>
            </div>
            <div class="flex items-start gap-2">
                <Coins class="mt-0.5 size-4 text-muted-foreground" />
                <div>
                    <dt class="text-muted-foreground">Biudžetas</dt>
                    <dd class="font-medium">
                        {{
                            formatBudget(
                                serviceRequest.budget_min_cents,
                                serviceRequest.budget_max_cents,
                            )
                        }}
                    </dd>
                </div>
            </div>
            <div class="flex items-start gap-2">
                <CalendarClock class="mt-0.5 size-4 text-muted-foreground" />
                <div>
                    <dt class="text-muted-foreground">Pradžia</dt>
                    <dd class="font-medium">
                        {{
                            serviceRequest.start_date
                                ? formatDate(serviceRequest.start_date)
                                : serviceRequest.start_preference
                        }}
                    </dd>
                </div>
            </div>
            <div class="flex items-start gap-2">
                <Lock class="mt-0.5 size-4 text-muted-foreground" />
                <div>
                    <dt class="text-muted-foreground">Adresas</dt>
                    <dd v-if="serviceRequest.address" class="font-medium">
                        {{ serviceRequest.address }}
                    </dd>
                    <dd
                        v-else-if="serviceRequest.address === null"
                        class="text-muted-foreground"
                    >
                        Nenurodytas
                    </dd>
                    <dd v-else class="text-muted-foreground">
                        Matomas tik išrinktam teikėjui
                    </dd>
                </div>
            </div>
        </dl>
    </section>
</template>
