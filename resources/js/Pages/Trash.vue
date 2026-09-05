<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import http from '@/composables/useHttp';
import { confirm, prompt } from '@/lib/swal';

defineProps({ notes: Array, workspaces: { type: Array, default: () => [] } });

async function restore(note) {
    await http.post(route('api.notes.restore', note.id));
    router.reload();
}
async function purge(note) {
    const ok = await confirm({
        title: 'Supprimer définitivement cette note ?',
        text: 'Cette action est irréversible.',
        confirmText: 'Supprimer',
        destructive: true,
    });
    if (!ok) {
        return;
    }
    await http.delete(route('api.notes.force', note.id));
    router.reload();
}

async function restoreWorkspace(ws) {
    await http.post(route('workspaces.restore', ws.id));
    router.reload();
}

async function purgeWorkspace(ws) {
    let confirmName = ws.name;
    if (ws.notes_count > 0) {
        confirmName = await prompt({
            title: 'Supprimer définitivement ce bureau ?',
            text: `Ce bureau contient ${ws.notes_count} note(s). Tapez « ${ws.name} » pour confirmer.`,
            inputLabel: 'Nom du bureau',
            inputPlaceholder: ws.name,
            expected: ws.name,
            confirmText: 'Supprimer',
            destructive: true,
        });
        if (!confirmName) {
            return;
        }
    } else {
        const ok = await confirm({
            title: 'Supprimer définitivement ce bureau ?',
            text: 'Cette action est irréversible.',
            confirmText: 'Supprimer',
            destructive: true,
        });
        if (!ok) {
            return;
        }
    }
    await http.delete(route('workspaces.force', ws.id), { data: { confirm_name: confirmName } });
    router.reload();
}
</script>
<template>
    <Head title="Corbeille" />
    <AppLayout>
        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="mb-6 text-2xl font-semibold">Corbeille</h1>
            <div v-for="ws in workspaces" :key="ws.id" class="mb-3 flex items-center justify-between rounded-2xl bg-white p-4 dark:bg-stone-900">
                <div>
                    <strong>{{ ws.icon }} {{ ws.name }}</strong>
                    <p class="text-xs text-stone-500">Bureau · {{ ws.notes_count }} notes</p>
                </div>
                <div class="flex gap-2 text-sm">
                    <button class="rounded-lg border px-3 py-1" @click="restoreWorkspace(ws)">Restaurer</button>
                    <button class="rounded-lg bg-red-600 px-3 py-1 text-white" @click="purgeWorkspace(ws)">Supprimer</button>
                </div>
            </div>
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
            <p v-if="!notes.length && !workspaces.length" class="text-stone-400">La corbeille est vide.</p>
        </div>
    </AppLayout>
</template>
