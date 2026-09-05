<script setup>
defineProps({
    tone: {
        type: String,
        default: 'neutral',
    },
});

const tones = {
    neutral: 'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-200',
    success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
    danger: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    info: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
};
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="tones[tone] || tones.neutral">
        <slot />
    </span>
</template>
