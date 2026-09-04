<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

defineProps({ users: Object, filters: Object });
const search = useForm({ q: '' });
const create = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'user',
});

function toggle(user) {
    router.patch(route('admin.users.update', user.id), { is_active: !user.is_active });
}
function setRole(user, role) {
    router.patch(route('admin.users.update', user.id), { role });
}
function remove(user) {
    if (!confirm('Désactiver et mettre cet utilisateur à la corbeille ? Ses bureaux restent jusqu’à purge.')) return;
    router.delete(route('admin.users.destroy', user.id));
}
function restore(user) {
    router.post(route('admin.users.restore', user.id));
}
</script>
<template>
    <Head title="Utilisateurs" />
    <AdminLayout>
        <h1 class="mb-4 text-2xl font-semibold">Utilisateurs</h1>
        <p class="mb-4 text-xs text-stone-500">Suppression = désactivation + soft delete. Les données restent jusqu’à purge. Un compte désactivé ou supprimé ne peut plus se connecter.</p>
        <form class="mb-4" @submit.prevent="router.get(route('admin.users.index'), { q: search.q })">
            <input v-model="search.q" class="rounded-lg border px-3 py-1 dark:bg-stone-900" placeholder="Nom ou email">
        </form>
        <form class="mb-6 grid gap-2 rounded-xl border p-3 text-sm dark:border-stone-700 sm:grid-cols-2" @submit.prevent="create.post(route('admin.users.store'), { onSuccess: () => create.reset() })">
            <input v-model="create.name" required placeholder="Nom" class="rounded border px-2 py-1 dark:bg-stone-950">
            <input v-model="create.email" type="email" required placeholder="Email" class="rounded border px-2 py-1 dark:bg-stone-950">
            <input v-model="create.password" type="password" required placeholder="Mot de passe fort" class="rounded border px-2 py-1 dark:bg-stone-950">
            <input v-model="create.password_confirmation" type="password" required placeholder="Confirmation" class="rounded border px-2 py-1 dark:bg-stone-950">
            <select v-model="create.role" class="rounded border px-2 py-1 dark:bg-stone-950">
                <option value="user">user</option>
                <option value="admin">admin</option>
            </select>
            <button class="rounded bg-orange-600 px-3 py-1 text-white" :disabled="create.processing">Créer</button>
        </form>
        <table class="w-full text-left text-sm">
            <tbody>
                <tr v-for="user in users.data" :key="user.id" class="border-b dark:border-stone-800">
                    <td class="py-2">
                        <Link :href="route('admin.users.show', user.id)" class="underline">{{ user.name }}</Link>
                        <br><span class="text-stone-400">{{ user.email }}</span>
                    </td>
                    <td>{{ user.role }}</td>
                    <td>{{ user.deleted_at ? 'corbeille' : (user.is_active ? 'actif' : 'off') }}</td>
                    <td class="space-x-2">
                        <button v-if="!user.deleted_at" class="underline" @click="toggle(user)">{{ user.is_active ? 'Désactiver' : 'Activer' }}</button>
                        <button v-if="!user.deleted_at" class="underline" @click="setRole(user, user.role === 'admin' ? 'user' : 'admin')">Rôle</button>
                        <button v-if="!user.deleted_at" class="text-red-600" @click="remove(user)">Supprimer</button>
                        <button v-else class="underline" @click="restore(user)">Restaurer</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </AdminLayout>
</template>
