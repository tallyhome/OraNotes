<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBadge from '@/Components/Admin/AdminBadge.vue';
import AdminEmptyState from '@/Components/Admin/AdminEmptyState.vue';
import AdminPagination from '@/Components/Admin/AdminPagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDateTime, paginatorRows, roleLabel } from '@/composables/useAdminUi';

const props = defineProps({
    users: Object,
    filters: Object,
});

const search = useForm({
    q: props.filters?.q || '',
    role: props.filters?.role || '',
    active: props.filters?.active ?? '',
});
const create = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'user',
});
const rows = computed(() => paginatorRows(props.users));

function apply() {
    router.get(route('admin.users.index'), {
        q: search.q || undefined,
        role: search.role || undefined,
        active: search.active === '' ? undefined : search.active,
    }, { preserveState: true, replace: true });
}

function toggle(user) {
    router.patch(route('admin.users.update', user.id), { is_active: !user.is_active });
}

function setRole(user, role) {
    router.patch(route('admin.users.update', user.id), { role });
}

function remove(user) {
    if (!confirm('Désactiver et mettre cet utilisateur à la corbeille ? Ses bureaux restent jusqu’à purge.')) {
        return;
    }
    router.delete(route('admin.users.destroy', user.id));
}

function restore(user) {
    router.post(route('admin.users.restore', user.id));
}
</script>

<template>
    <Head title="Utilisateurs" />
    <AdminLayout title="Utilisateurs" description="Création, rôles, désactivation et restauration. Un compte désactivé ou supprimé ne peut plus se connecter.">
        <section class="mb-6 rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
            <h2 class="mb-3 text-sm font-semibold">Créer un utilisateur</h2>
            <form class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="create.post(route('admin.users.store'), { onSuccess: () => create.reset() })">
                <input v-model="create.name" required placeholder="Nom" class="rounded-lg border border-stone-300 px-3 py-2 dark:border-stone-700 dark:bg-stone-950">
                <input v-model="create.email" type="email" required placeholder="Email" class="rounded-lg border border-stone-300 px-3 py-2 dark:border-stone-700 dark:bg-stone-950">
                <select v-model="create.role" class="rounded-lg border border-stone-300 px-3 py-2 dark:border-stone-700 dark:bg-stone-950">
                    <option value="user">Utilisateur</option>
                    <option value="admin">Administrateur</option>
                </select>
                <input v-model="create.password" type="password" required placeholder="Mot de passe fort" class="rounded-lg border border-stone-300 px-3 py-2 dark:border-stone-700 dark:bg-stone-950">
                <input v-model="create.password_confirmation" type="password" required placeholder="Confirmation" class="rounded-lg border border-stone-300 px-3 py-2 dark:border-stone-700 dark:bg-stone-950">
                <button class="rounded-lg bg-orange-600 px-3 py-2 font-medium text-white" :disabled="create.processing">Créer</button>
            </form>
            <p v-if="create.errors.email || create.errors.password" class="mt-2 text-sm text-red-600">
                {{ create.errors.email || create.errors.password }}
            </p>
        </section>

        <form class="mb-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="apply">
            <input v-model="search.q" class="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900 sm:max-w-sm" placeholder="Nom ou email">
            <select v-model="search.role" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900" @change="apply">
                <option value="">Tous les rôles</option>
                <option value="user">Utilisateur</option>
                <option value="admin">Administrateur</option>
            </select>
            <select v-model="search.active" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900" @change="apply">
                <option value="">Tous les statuts</option>
                <option value="1">Actifs</option>
                <option value="0">Désactivés</option>
            </select>
            <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white">Filtrer</button>
        </form>

        <AdminEmptyState
            v-if="!rows.length"
            title="Aucun utilisateur"
            description="Aucun compte ne correspond à cette recherche."
        />

        <div v-else class="overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-400 dark:bg-stone-950/60">
                        <tr>
                            <th class="px-4 py-3 font-medium">Utilisateur</th>
                            <th class="px-4 py-3 font-medium">Rôle</th>
                            <th class="px-4 py-3 font-medium">Statut</th>
                            <th class="px-4 py-3 font-medium">Créé</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 dark:divide-stone-800">
                        <tr v-for="user in rows" :key="user.id">
                            <td class="px-4 py-3">
                                <Link :href="route('admin.users.show', user.id)" class="font-medium text-orange-800 underline dark:text-orange-300">{{ user.name }}</Link>
                                <p class="text-xs text-stone-400">{{ user.email }}</p>
                            </td>
                            <td class="px-4 py-3">{{ roleLabel(user.role) }}</td>
                            <td class="px-4 py-3">
                                <AdminBadge v-if="user.deleted_at" tone="danger">Corbeille</AdminBadge>
                                <AdminBadge v-else-if="user.is_active" tone="success">Actif</AdminBadge>
                                <AdminBadge v-else tone="warning">Désactivé</AdminBadge>
                            </td>
                            <td class="px-4 py-3 text-stone-500">{{ formatDateTime(user.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <template v-if="!user.deleted_at">
                                        <button type="button" class="underline" @click="toggle(user)">{{ user.is_active ? 'Désactiver' : 'Activer' }}</button>
                                        <button type="button" class="underline" @click="setRole(user, user.role === 'admin' ? 'user' : 'admin')">
                                            {{ user.role === 'admin' ? 'Rétrograder' : 'Promouvoir' }}
                                        </button>
                                        <button type="button" class="text-red-600" @click="remove(user)">Supprimer</button>
                                    </template>
                                    <button v-else type="button" class="underline" @click="restore(user)">Restaurer</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <AdminPagination :paginator="users" />
        </div>
    </AdminLayout>
</template>
