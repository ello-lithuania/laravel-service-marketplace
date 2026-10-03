<script setup lang="ts">
// Daugiaeilis laukas tokio pat stiliaus kaip ui/input (shadcn-vue textarea komponento projekte dar nėra)
const model = defineModel<string>({ default: '' });

defineProps<{
    id?: string;
    name?: string;
    rows?: number;
    placeholder?: string;
    maxlength?: number;
    invalid?: boolean;
}>();
</script>

<template>
    <div class="grid gap-1">
        <textarea
            :id="id"
            v-model="model"
            :name="name"
            :rows="rows ?? 5"
            :placeholder="placeholder"
            :maxlength="maxlength"
            :aria-invalid="invalid || undefined"
            class="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30"
        />
        <p v-if="maxlength" class="text-right text-xs text-muted-foreground">
            {{ model.length }} / {{ maxlength }}
        </p>
    </div>
</template>
