<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
defineProps({
    health: Object, php: String, laravel: String, env: String, debug: Boolean,
    url: String, db: String, cache: String, queue: String, session: String,
    version: String, oraeditor: String, node_runtime_required: Boolean,
});
</script>
<template>
    <Head title="Système" />
    <AdminLayout>
        <h1 class="mb-4 text-2xl font-semibold">Système</h1>
        <dl class="grid gap-2 text-sm sm:grid-cols-2">
            <div>PHP {{ php }}</div>
            <div>Laravel {{ laravel }}</div>
            <div>Env {{ env }} · debug {{ debug ? 'on' : 'off' }}</div>
            <div>{{ url }}</div>
            <div>DB {{ db }} · cache {{ cache }} · queue {{ queue }} · session {{ session }}</div>
            <div>OraNotes {{ version }} · OraEditor {{ oraeditor }}</div>
            <div>Node en production : {{ node_runtime_required ? 'requis' : 'non requis (assets précompilés)' }}</div>
        </dl>
    </AdminLayout>
</template>
