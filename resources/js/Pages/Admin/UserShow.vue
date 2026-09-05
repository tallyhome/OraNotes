<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBadge from '@/Components/Admin/AdminBadge.vue';
import AdminEmptyState from '@/Components/Admin/AdminEmptyState.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { activityLabel, formatDateTime, roleLabel } from '@/composables/useAdminUi';

const props = defineProps({
    user: Object,
    workspaces: Array,
    notes: Array,
    activity: Array,
});

const password = useForm({ password: '', password_confirmation: '' });

function resetPassword() {
    password.post(route('admin.users.password', props.user.id), { onSuccess: () => password.reset() });
}
</script>

<template>
    <Head :title="user.name" />
    <AdminLayout :title="user.name" :description="`${user.email} · ${roleLabel(user.role)} · ${user.workspaces_count} bureaux · ${user.notes_count} notes`">
        <template #actions>
            <Link href="/admin/users" class="rounded-lg border border-stone-200 px-3 py-1.5 text-sm dark:border-stone-700">Retour à la liste</Link>
        </template>

        <div class="mb-6 flex flex-wrap items-center gap-2">
            <AdminBadge :tone="user.is_active ? 'success' : 'warning'">{{ user.is_active ? 'Actif' : 'Désactivé' }}</AdminBadge>
            <AdminBadge tone="neutral">{{ roleLabel(user.role) }}</AdminBadge>
            <button type="button" class="rounded-lg border border-stone-200 px-3 py-1.5 text-sm dark:border-stone-700" @click="router.post(route('admin.users.sessions', user.id))">
                Révoquer les sessions
            </button>
        </div>

        <section class="mb-8 rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
            <h2 class="mb-3 text-sm font-semibold">Réinitialiser le mot de passe</h2>
            <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="resetPassword">
                <input v-model="password.password" type="password" required placeholder="Nouveau mot de passe" class="rounded-lg border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950">
                <input v-model="password.password_confirmation" type="password" required placeholder="Confirmation" class="rounded-lg border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950">
                <button class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white">Réinitialiser</button>
            </form>
            <p v-if="password.errors.password" class="mt-2 text-sm text-red-600">{{ password.errors.password }}</p>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
                <h2 class="border-b border-stone-200 px-4 py-3 text-sm font-semibold dark:border-stone-800">Bureaux</h2>
                <AdminEmptyState v-if="!workspaces?.length" title="Aucun bureau" />
                <ul v-else class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                    <li v-for="ws in workspaces" :key="ws.uuid" class="flex items-center justify-between gap-2 px-4 py-3">
                        <span>{{ ws.name }}</span>
                        <AdminBadge v-if="ws.is_locked" tone="warning">Verrouillé</AdminBadge>
                    </li>
                </ul>
            </section>
            <section class="rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
                <h2 class="border-b border-stone-200 px-4 py-3 text-sm font-semibold dark:border-stone-800">Notes</h2>
                <AdminEmptyState v-if="!notes?.length" title="Aucune note" />
                <ul v-else class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                    <li v-for="note in notes" :key="note.uuid" class="px-4 py-3">{{ note.title }}</li>
                </ul>
            </section>
        </div>

        <section class="mt-6 rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <h2 class="border-b border-stone-200 px-4 py-3 text-sm font-semibold dark:border-stone-800">Activité</h2>
            <AdminEmptyState v-if="!activity?.length" title="Aucune activité" />
            <ul v-else class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                <li v-for="log in activity" :key="log.id" class="flex flex-wrap justify-between gap-2 px-4 py-3">
                    <span>{{ activityLabel(log.action) }} · {{ log.ip_address || '—' }}</span>
                    <span class="text-xs text-stone-400">{{ formatDateTime(log.created_at) }}</span>
                </li>
            </ul>
        </section>
    </AdminLayout>
</template>
