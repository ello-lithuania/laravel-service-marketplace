<script setup lang="ts">
import { BadgeCheck } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

// „Patikrintas" – administratorius patikrino teikėjo duomenis (verified_at). Tas pats kortelėje ir profilyje.
</script>

<template>
    <Badge
        variant="secondary"
        class="gap-1 border-primary/15 bg-secondary font-medium text-secondary-foreground"
        title="Administratorius patikrino teikėjo duomenis"
    >
        <BadgeCheck class="text-primary" aria-hidden="true" />
        Patikrintas
    </Badge>
</template>
