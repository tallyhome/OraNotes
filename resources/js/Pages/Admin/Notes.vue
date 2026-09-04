<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
defineProps({ notes: Object, filters: Object });

function purge(note) {
    if (!confirm('Supprimer définitivement cette note ?')) return;
    router.delete(route('admin.notes.destroy', note.id));
}
</script>
<template>
    <Head title="Modération notes" />
    <AdminLayout>
        <h1 class="mb-4 text-2xl font-semibold">Notes</h1>
        <div v-for="note in notes.data" :key="note.id" class="mb-2 flex justify-between rounded-xl bg-white p-3 text-sm dark:bg-stone-900">
            <div>
                <strong>{{ note.title }}</strong>
                <p class="text-stone-400">{{ note.author_email }} · {{ note.is_trashed ? 'corbeille' : (note.is_archived ? 'archivée' : 'active') }}</p>
            </div>
            <div class="flex gap-2">
                <button v-if="note.is_archived || note.is_trashed" class="underline" @click="router.post(route('admin.notes.restore', note.id))">Restaurer</button>
                <button class="text-red-600" @click="purge(note)">Purger</button>
            </div>
        </div>
    </AdminLayout>
</template>
