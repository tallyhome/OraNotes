<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    paginator: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <nav
        v-if="paginator.last_page > 1"
        class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-200 px-4 py-3 text-sm dark:border-stone-800"
    >
        <p class="text-stone-500">
            {{ paginator.from || 0 }}–{{ paginator.to || 0 }} sur {{ paginator.total }}
        </p>
        <div class="flex items-center gap-2">
            <Link
                v-if="paginator.prev_page_url"
                :href="paginator.prev_page_url"
                class="rounded-lg border border-stone-200 px-3 py-1.5 hover:bg-stone-50 dark:border-stone-700 dark:hover:bg-stone-800"
            >
                Précédent
            </Link>
            <span v-else class="rounded-lg border border-stone-200 px-3 py-1.5 text-stone-400 dark:border-stone-800">Précédent</span>
            <span class="text-stone-500">Page {{ paginator.current_page }} / {{ paginator.last_page }}</span>
            <Link
                v-if="paginator.next_page_url"
                :href="paginator.next_page_url"
                class="rounded-lg border border-stone-200 px-3 py-1.5 hover:bg-stone-50 dark:border-stone-700 dark:hover:bg-stone-800"
            >
                Suivant
            </Link>
            <span v-else class="rounded-lg border border-stone-200 px-3 py-1.5 text-stone-400 dark:border-stone-800">Suivant</span>
        </div>
    </nav>
</template>
