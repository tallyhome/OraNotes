<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
defineProps({ workspaces: Object, filters: Object });

function purge(ws) {
    const name = window.prompt(`Tapez « ${ws.name} » pour supprimer définitivement ce bureau.`);
    if (name !== ws.name) return;
    router.delete(route('admin.workspaces.destroy', ws.id), { data: { confirm_name: name } });
}
</script>
<template>
    <Head title="Bureaux" />
    <AdminLayout>
        <h1 class="mb-4 text-2xl font-semibold">Bureaux</h1>
        <div v-for="ws in workspaces.data" :key="ws.id" class="mb-2 flex justify-between gap-3 rounded-xl bg-white p-3 text-sm dark:bg-stone-900">
            <div>{{ ws.icon }} {{ ws.name }} · {{ ws.owner_email }} {{ ws.is_locked ? '🔒' : '' }}</div>
            <div class="flex flex-wrap gap-2">
                <button class="underline" @click="router.post(route(ws.is_locked ? 'admin.workspaces.unlock' : 'admin.workspaces.lock', ws.id))">
                    {{ ws.is_locked ? 'Unlock' : 'Lock' }}
                </button>
                <button v-if="ws.is_archived || ws.is_trashed" class="underline" @click="router.post(route('admin.workspaces.restore', ws.id))">Restaurer</button>
                <button class="text-red-600" @click="purge(ws)">Supprimer</button>
            </div>
        </div>
    </AdminLayout>
</template>
