<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import http from '@/composables/useHttp';

const props = defineProps({ notes: Array });

async function restore(note) {
    await http.post(route('api.notes.restore', note.id));
    router.reload();
}
async function purge(note) {
    if (!confirm('Supprimer définitivement cette note ?')) return;
    await http.delete(route('api.notes.force', note.id));
    router.reload();
}
</script>
<template>
    <Head title="Corbeille" />
    <AppLayout>
        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="mb-6 text-2xl font-semibold">Corbeille</h1>
            <div v-for="note in notes" :key="note.id" class="mb-3 flex items-center justify-between rounded-2xl bg-white p-4 dark:bg-stone-900">
                <div>
                    <strong>{{ note.title }}</strong>
                    <p class="text-xs text-stone-500">Supprimée {{ note.deleted_at }}</p>
                </div>
                <div class="flex gap-2 text-sm">
                    <button class="rounded-lg border px-3 py-1" @click="restore(note)">Restaurer</button>
                    <button class="rounded-lg bg-red-600 px-3 py-1 text-white" @click="purge(note)">Supprimer</button>
                </div>
            </div>
            <p v-if="!notes.length" class="text-stone-400">La corbeille est vide.</p>
        </div>
    </AppLayout>
</template>
