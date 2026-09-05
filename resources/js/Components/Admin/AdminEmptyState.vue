<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center dark:border-stone-700 dark:bg-stone-900">
        <p class="text-sm font-semibold text-stone-800 dark:text-stone-100">{{ title }}</p>
        <p v-if="description" class="mx-auto mt-2 max-w-md text-sm text-stone-500">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
