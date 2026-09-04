<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ notes: Array });
</script>
<template>
    <Head title="Favoris" />
    <AppLayout>
        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="mb-6 text-2xl font-semibold">Favoris</h1>
            <Link v-for="note in notes" :key="note.id" :href="`/workspaces/${note.workspace_id}?note=${note.id}`" class="mb-3 block rounded-2xl bg-white p-4 dark:bg-stone-900">
                <strong>{{ note.title }}</strong>
                <p class="text-sm text-stone-500">{{ note.status }}</p>
            </Link>
            <p v-if="!notes.length" class="text-stone-400">Étoilez une note depuis le bureau.</p>
        </div>
    </AppLayout>
</template>
