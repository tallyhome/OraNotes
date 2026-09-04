<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps({ user: Object, workspaces: Array, notes: Array, activity: Array });
const password = useForm({ password: '', password_confirmation: '' });

function resetPassword() {
    password.post(route('admin.users.password', props.user.id), { onSuccess: () => password.reset() });
}
</script>
<template>
    <Head :title="user.name" />
    <AdminLayout>
        <h1 class="mb-2 text-2xl font-semibold">{{ user.name }}</h1>
        <p class="mb-6 text-sm text-stone-500">{{ user.email }} · {{ user.role }} · {{ user.workspaces_count }} bureaux · {{ user.notes_count }} notes</p>
        <div class="mb-6 flex flex-wrap gap-2 text-sm">
            <button class="rounded border px-3 py-1" @click="router.post(route('admin.users.sessions', user.id))">Révoquer les sessions</button>
        </div>
        <form class="mb-8 flex flex-wrap gap-2 text-sm" @submit.prevent="resetPassword">
            <input v-model="password.password" type="password" required placeholder="Nouveau mot de passe" class="rounded border px-2 py-1 dark:bg-stone-950">
            <input v-model="password.password_confirmation" type="password" required placeholder="Confirmation" class="rounded border px-2 py-1 dark:bg-stone-950">
            <button class="rounded bg-orange-600 px-3 py-1 text-white">Réinitialiser</button>
        </form>
        <h2 class="mb-2 font-semibold">Bureaux</h2>
        <ul class="mb-6 text-sm">
            <li v-for="ws in workspaces" :key="ws.uuid">{{ ws.name }} {{ ws.is_locked ? '🔒' : '' }}</li>
        </ul>
        <h2 class="mb-2 font-semibold">Notes</h2>
        <ul class="mb-6 text-sm">
            <li v-for="note in notes" :key="note.uuid">{{ note.title }}</li>
        </ul>
        <h2 class="mb-2 font-semibold">Activité</h2>
        <div v-for="log in activity" :key="log.id" class="rounded bg-white px-3 py-2 text-xs dark:bg-stone-900">
            {{ log.created_at }} · {{ log.action }} · {{ log.ip_address }}
        </div>
    </AdminLayout>
</template>
