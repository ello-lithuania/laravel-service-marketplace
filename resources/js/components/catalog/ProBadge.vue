<script setup lang="ts">
import { Crown } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

// Etapas 9c: ženklelis teikėjams su galiojančia prenumerata, kurios plane badge = true
// (has_pro_badge iš ProviderCardResource / ProviderProfileResource). Tas pats katalogo kortelėje ir profilyje.
// Etapas 10: prekės ženklo oranžinė (cta) – kaip ir pagrindiniai veiksmai.
</script>

<template>
    <Badge
        variant="secondary"
        class="gap-1 border-cta/30 bg-cta/15 font-semibold tracking-wide text-[#8a4b0c] dark:text-cta"
        title="Teikėjas turi PRO prenumeratą"
        data-test="pro-badge"
    >
        <Crown aria-hidden="true" />
        PRO
    </Badge>
</template>
