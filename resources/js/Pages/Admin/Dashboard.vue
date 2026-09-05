<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminKpiCard from '@/Components/Admin/AdminKpiCard.vue';
import AdminEmptyState from '@/Components/Admin/AdminEmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';
import { activityLabel, formatBytes, formatDateTime } from '@/composables/useAdminUi';

defineProps({
    stats: Object,
    recentActivity: Array,
    update: Object,
});

function healthLabel(stats) {
    const health = stats?.health;
    if (!health) {
        return 'Inconnu';
    }
    if (health.error > 0) {
        return 'Incidents';
    }
    if (health.warning > 0) {
        return 'Attention';
    }

    return 'Sain';
}
</script>

<template>
    <Head title="Tableau de bord" />
    <AdminLayout title="Tableau de bord" description="Vue d’ensemble de l’instance OraNotes.">
        <div
            v-if="update?.available"
            class="mb-6 rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-100"
        >
            Une mise à jour {{ update.latest }} est disponible (version actuelle {{ stats.oranotes_version }}).
            <Link href="/admin/updates" class="ml-2 font-medium underline">Voir les mises à jour</Link>
        </div>
        <div
            v-else-if="update?.error_code === 'ssl_ca'"
            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
        >
            Vérification des mises à jour bloquée : certificat SSL / bundle CA manquant.
            <Link href="/admin/updates" class="ml-2 font-medium underline">Voir la marche à suivre</Link>
        </div>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <AdminKpiCard label="Utilisateurs" :value="stats.users" :hint="`${stats.active_users} actifs · ${stats.disabled_users} désactivés · ${stats.new_users_7d} sur 7 j`" />
            <AdminKpiCard label="Bureaux" :value="stats.workspaces" :hint="`${stats.locked_workspaces} verrouillés · ${stats.archived_workspaces || 0} archivés`" />
            <AdminKpiCard label="Notes" :value="stats.notes" :hint="`${stats.archived_notes} archivées · ${stats.trashed_notes} en corbeille`" />
            <AdminKpiCard label="Stockage pièces jointes" :value="formatBytes(stats.storage_bytes)" :hint="`${stats.attachments} fichier(s)`" />
            <AdminKpiCard label="Version" :value="stats.oranotes_version" :hint="`OraEditor ${stats.oraeditor_version}`" />
            <AdminKpiCard label="Santé" :value="healthLabel(stats)" :hint="`${stats.health?.ok || 0} OK · ${stats.health?.warning || 0} alertes · ${stats.health?.error || 0} erreurs`" />
            <AdminKpiCard label="Activité 24 h" :value="stats.activity_24h" :hint="`${stats.share_links} liens de partage`" />
            <AdminKpiCard label="Jobs en échec" :value="stats.failed_jobs" :hint="`Cache ${stats.cache} · files ${stats.queue}`" />
        </div>

        <section class="mb-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-400">Actions rapides</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link href="/admin/users" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm hover:border-orange-300 dark:border-stone-800 dark:bg-stone-900">
                    <p class="font-medium">Créer un utilisateur</p>
                    <p class="mt-1 text-stone-500">Compte, rôle et mot de passe</p>
                </Link>
                <Link href="/admin/workspaces" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm hover:border-orange-300 dark:border-stone-800 dark:bg-stone-900">
                    <p class="font-medium">Voir les bureaux</p>
                    <p class="mt-1 text-stone-500">Modération, verrouillage, purge</p>
                </Link>
                <Link href="/admin/health" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm hover:border-orange-300 dark:border-stone-800 dark:bg-stone-900">
                    <p class="font-medium">Santé</p>
                    <p class="mt-1 text-stone-500">Contrôles PHP, disque, cache</p>
                </Link>
                <Link href="/admin/updates" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm hover:border-orange-300 dark:border-stone-800 dark:bg-stone-900">
                    <p class="font-medium">Mises à jour</p>
                    <p class="mt-1 text-stone-500">Releases GitHub officielles</p>
                </Link>
            </div>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <div class="flex items-center justify-between gap-3 border-b border-stone-200 px-4 py-3 dark:border-stone-800">
                <h2 class="text-sm font-semibold">Activité récente</h2>
                <Link href="/admin/activity" class="text-sm text-orange-700 hover:underline">Journal complet</Link>
            </div>
            <AdminEmptyState
                v-if="!recentActivity?.length"
                title="Aucune activité récente"
                description="Les connexions et actions d’administration apparaîtront ici."
            />
            <ul v-else class="divide-y divide-stone-100 dark:divide-stone-800">
                <li v-for="item in recentActivity" :key="item.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                    <div>
                        <p class="font-medium">{{ activityLabel(item.action) }}</p>
                        <p class="text-stone-500">{{ item.user || 'Système' }} · {{ item.ip_address || 'IP inconnue' }}</p>
                    </div>
                    <p class="text-xs text-stone-400">{{ formatDateTime(item.created_at) }}</p>
                </li>
            </ul>
        </section>
    </AdminLayout>
</template>
