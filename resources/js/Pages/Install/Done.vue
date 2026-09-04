<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ url: String, version: String });
</script>
<template>
    <GuestLayout>
        <Head title="Installation terminée" />
        <h1 class="mb-3 text-xl font-semibold">OraNotes {{ version }} est installé</h1>
        <p class="mb-2 text-sm">URL : {{ url }}</p>
        <p class="mb-4 text-sm">Le wizard est verrouillé. En production : APP_DEBUG=false, HTTPS, document root = public/.</p>
        <Link href="/login" class="rounded bg-orange-600 px-4 py-2 text-white">Connexion admin</Link>
    </GuestLayout>
</template>
