<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ notes: Array });
</script>
<template>
    <Head title="Partagés" />
    <AppLayout>
        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="mb-6 text-2xl font-semibold">Partagés avec moi</h1>
            <Link v-for="note in notes" :key="note.id" :href="`/workspaces/${note.workspace_id}?note=${note.id}`" class="mb-3 block rounded-2xl bg-white p-4 dark:bg-stone-900">
                {{ note.title }}
            </Link>
        </div>
    </AppLayout>
</template>
