<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        default: '—',
    },
    hint: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
        <p class="text-xs font-medium uppercase tracking-wide text-stone-400">{{ label }}</p>
        <p class="mt-2 text-2xl font-semibold tracking-tight">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-stone-500">{{ hint }}</p>
    </div>
</template>
