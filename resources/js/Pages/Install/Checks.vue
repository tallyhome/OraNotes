<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ environment: Object, requirements: Array, ready: Boolean });
const form = useForm({
    app: { name: 'OraNotes', url: window.location.origin, env: 'production', locale: 'fr' },
    database: { driver: 'sqlite', host: '127.0.0.1', port: 3306, database: 'database/database.sqlite', username: '', password: '' },
    admin: { name: '', email: '', password: '', password_confirmation: '' },
});
</script>
<template>
    <GuestLayout>
        <Head title="Contrôles système" />
        <h1 class="mb-3 text-xl font-semibold">Contrôles système</h1>
        <ul class="mb-4 text-sm">
            <li v-for="item in requirements" :key="item.name">
                <span :class="item.ok ? 'text-green-700' : 'text-red-600'">{{ item.ok ? 'OK' : 'KO' }}</span>
                {{ item.name }} — {{ item.detail }}
            </li>
        </ul>
        <form v-if="ready" class="space-y-2 text-sm" @submit.prevent="form.post(route('install.store'))">
            <input v-model="form.app.name" class="w-full rounded border px-2 py-1" placeholder="Nom de l’app">
            <input v-model="form.app.url" class="w-full rounded border px-2 py-1" placeholder="URL">
            <select v-model="form.database.driver" class="w-full rounded border px-2 py-1">
                <option value="sqlite">SQLite</option>
                <option value="mysql">MySQL / MariaDB</option>
            </select>
            <template v-if="form.database.driver === 'mysql'">
                <input v-model="form.database.host" class="w-full rounded border px-2 py-1" placeholder="Hôte">
                <input v-model="form.database.port" class="w-full rounded border px-2 py-1" placeholder="Port">
                <input v-model="form.database.database" class="w-full rounded border px-2 py-1" placeholder="Base">
                <input v-model="form.database.username" class="w-full rounded border px-2 py-1" placeholder="Utilisateur">
                <input v-model="form.database.password" type="password" class="w-full rounded border px-2 py-1" placeholder="Mot de passe DB">
            </template>
            <input v-model="form.admin.name" required class="w-full rounded border px-2 py-1" placeholder="Admin — nom">
            <input v-model="form.admin.email" type="email" required class="w-full rounded border px-2 py-1" placeholder="Admin — email">
            <input v-model="form.admin.password" type="password" required class="w-full rounded border px-2 py-1" placeholder="Mot de passe fort">
            <input v-model="form.admin.password_confirmation" type="password" required class="w-full rounded border px-2 py-1" placeholder="Confirmation">
            <button class="rounded bg-orange-600 px-4 py-2 text-white" :disabled="form.processing">Installer</button>
            <p v-if="form.errors.install || form.errors.database" class="text-red-600">{{ form.errors.install || form.errors.database }}</p>
        </form>
        <p v-else class="text-sm text-red-600">Corrigez les dépendances manquantes avant d’installer. OraNotes ne simulera pas un environnement incompatible.</p>
    </GuestLayout>
</template>
