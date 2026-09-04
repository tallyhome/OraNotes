<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
const props = defineProps({ status: Object, compatibility: Object, repository: String });

function apply() {
    if (!confirm('Sauvegarder puis appliquer la mise à jour officielle ? Un rollback n’est pas atomique.')) return;
    router.post(route('admin.updates.apply'));
}
</script>
<template>
    <Head title="Mises à jour" />
    <AdminLayout>
        <h1 class="mb-4 text-2xl font-semibold">Mises à jour</h1>
        <p class="mb-2 text-sm">Source officielle : GitHub {{ repository }} — aucune URL arbitraire n’est acceptée.</p>
        <p class="mb-2">Actuelle : <strong>{{ status.current }}</strong></p>
        <p class="mb-2">Disponible : <strong>{{ status.latest || '—' }}</strong></p>
        <pre v-if="status.changelog" class="mb-4 max-h-64 overflow-auto rounded bg-stone-100 p-3 text-xs dark:bg-stone-900">{{ status.changelog }}</pre>
        <ul v-if="compatibility?.errors?.length" class="mb-4 text-sm text-red-600">
            <li v-for="err in compatibility.errors" :key="err">{{ err }}</li>
        </ul>
        <button
            v-if="status.available && compatibility?.ok"
            class="rounded bg-orange-600 px-4 py-2 text-white"
            @click="apply"
        >
            Télécharger et appliquer
        </button>
        <p v-else class="text-sm text-stone-500">Pas d’application possible pour le moment.</p>
    </AdminLayout>
</template>
