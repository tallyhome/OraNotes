<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ stats: Object, recentActivity: Array, update: Object });
</script>
<template>
    <Head title="Admin" />
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">Tableau de bord</h1>
        <p class="mb-4 text-sm text-stone-500">
            OraNotes {{ stats.oranotes_version }} · OraEditor {{ stats.oraeditor_version }}
            <span v-if="update?.available" class="ml-2 text-orange-700">Mise à jour {{ update.latest }} disponible</span>
        </p>
        <div class="mb-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="key in ['users','active_users','disabled_users','new_users_7d','workspaces','locked_workspaces','notes','archived_notes','trashed_notes','attachments','share_links','versions','activity_24h','failed_jobs']" :key="key" class="rounded-2xl bg-white p-4 dark:bg-stone-900">
                <p class="text-xs uppercase text-stone-400">{{ key.replaceAll('_', ' ') }}</p>
                <p class="text-2xl font-semibold">{{ stats[key] }}</p>
            </div>
        </div>
        <p class="mb-6 text-sm">Stockage pièces jointes : {{ Math.round((stats.storage_bytes || 0) / 1024) }} Kio · cache {{ stats.cache }} · files {{ stats.queue }}</p>
        <div class="mb-6 text-sm">
            <Link href="/admin/health" class="underline">Santé</Link>
            ·
            <Link href="/admin/updates" class="underline">Mises à jour</Link>
        </div>
        <div class="space-y-2 text-sm">
            <div v-for="item in recentActivity" :key="item.id" class="rounded-lg bg-white px-3 py-2 dark:bg-stone-900">
                {{ item.user }} · {{ item.action }} · {{ item.ip_address }}
            </div>
        </div>
    </AdminLayout>
</template>
