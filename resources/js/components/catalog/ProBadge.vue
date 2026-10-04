<script setup lang="ts">
import { Crown } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

// Etapas 9c: ženklelis teikėjams su galiojančia prenumerata, kurios plane badge = true
// (has_pro_badge iš ProviderCardResource / ProviderProfileResource). Tas pats katalogo kortelėje ir profilyje.
</script>

<template>
    <Badge
        variant="secondary"
        class="bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300"
        title="Teikėjas turi PRO prenumeratą"
        data-test="pro-badge"
    >
        <Crown aria-hidden="true" />
        PRO
    </Badge>
</template>
