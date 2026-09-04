<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
defineProps({ users: Object, filters: Object });
const search = useForm({ q: '' });
function toggle(user) {
    router.patch(route('admin.users.update', user.id), { is_active: !user.is_active });
}
function setRole(user, role) {
    router.patch(route('admin.users.update', user.id), { role });
}
</script>
<template>
    <Head title="Utilisateurs" />
    <AppLayout>
        <div class="mx-auto max-w-5xl px-6 py-8">
            <h1 class="mb-4 text-2xl font-semibold">Utilisateurs</h1>
            <form class="mb-4" @submit.prevent="router.get(route('admin.users.index'), { q: search.q })">
                <input v-model="search.q" class="rounded-lg border px-3 py-1 dark:bg-stone-900" placeholder="Nom ou email">
            </form>
            <table class="w-full text-left text-sm">
                <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="border-b dark:border-stone-800">
                        <td class="py-2">{{ user.name }}<br><span class="text-stone-400">{{ user.email }}</span></td>
                        <td>{{ user.role }}</td>
                        <td>{{ user.is_active ? 'actif' : 'off' }}</td>
                        <td class="space-x-2">
                            <button class="underline" @click="toggle(user)">{{ user.is_active ? 'Désactiver' : 'Activer' }}</button>
                            <button class="underline" @click="setRole(user, user.role === 'admin' ? 'user' : 'admin')">Rôle</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
