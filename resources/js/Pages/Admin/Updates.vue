<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBadge from '@/Components/Admin/AdminBadge.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    status: Object,
    compatibility: Object,
    repository: String,
});

function apply() {
    if (!confirm('Sauvegarder puis appliquer la mise à jour officielle ? Un rollback n’est pas atomique.')) {
        return;
    }
    router.post(route('admin.updates.apply'));
}

function stateLabel(status) {
    if (status?.available) {
        return 'Mise à jour disponible';
    }
    if (status?.error) {
        return 'Vérification impossible';
    }

    return 'À jour';
}

function stateTone(status) {
    if (status?.available) {
        return 'warning';
    }
    if (status?.error_code === 'ssl_ca') {
        return 'warning';
    }
    if (status?.error) {
        return 'danger';
    }

    return 'success';
}
</script>

<template>
    <Head title="Mises à jour" />
    <AdminLayout title="Mises à jour" description="Source officielle : GitHub uniquement. Aucune URL arbitraire n’est acceptée.">
        <div class="mb-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">Dépôt</p>
                <p class="mt-1 font-medium">{{ repository }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">État</p>
                <p class="mt-2">
                    <AdminBadge :tone="stateTone(status)">{{ stateLabel(status) }}</AdminBadge>
                </p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">Version actuelle</p>
                <p class="mt-1 text-xl font-semibold">{{ status.current }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">Version disponible</p>
                <p class="mt-1 text-xl font-semibold">{{ status.latest || '—' }}</p>
            </div>
        </div>

        <section
            v-if="status.error_code === 'ssl_ca'"
            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
        >
            <h2 class="font-semibold">Certificat SSL manquant / bundle CA</h2>
            <p class="mt-2">{{ status.error }}</p>
            <p class="mt-3 font-medium">Marche à suivre</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li v-for="step in status.remediation" :key="step">{{ step }}</li>
            </ol>
        </section>
        <section
            v-else-if="status.error"
            class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200"
        >
            <p>{{ status.error }}</p>
        </section>

        <pre v-if="status.changelog" class="mb-4 max-h-64 overflow-auto rounded-2xl bg-stone-100 p-4 text-xs dark:bg-stone-900">{{ status.changelog }}</pre>
        <ul v-if="compatibility?.errors?.length" class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30">
            <li v-for="err in compatibility.errors" :key="err">{{ err }}</li>
        </ul>
        <button
            v-if="status.available && compatibility?.ok"
            class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white"
            @click="apply"
        >
            Télécharger et appliquer
        </button>
        <p v-else class="text-sm text-stone-500">Pas d’application possible pour le moment.</p>
    </AdminLayout>
</template>
