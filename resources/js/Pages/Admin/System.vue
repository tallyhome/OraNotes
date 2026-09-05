<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    health: Object,
    php: String,
    laravel: String,
    env: String,
    debug: Boolean,
    url: String,
    db: String,
    cache: String,
    queue: String,
    session: String,
    version: String,
    oraeditor: String,
    node_runtime_required: Boolean,
});
</script>

<template>
    <Head title="Système" />
    <AdminLayout title="Système" description="Informations d’exécution de cette instance. Lecture seule.">
        <dl class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase tracking-wide text-stone-400">PHP</dt>
                <dd class="mt-1 font-medium">{{ php }}</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase tracking-wide text-stone-400">Laravel</dt>
                <dd class="mt-1 font-medium">{{ laravel }}</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase tracking-wide text-stone-400">Environnement</dt>
                <dd class="mt-1 font-medium">{{ env }} · debug {{ debug ? 'activé' : 'désactivé' }}</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase tracking-wide text-stone-400">URL</dt>
                <dd class="mt-1 break-all font-medium">{{ url }}</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase tracking-wide text-stone-400">Infrastructure</dt>
                <dd class="mt-1 font-medium">DB {{ db }} · cache {{ cache }} · files {{ queue }} · session {{ session }}</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900">
                <dt class="text-xs uppercase tracking-wide text-stone-400">Versions</dt>
                <dd class="mt-1 font-medium">OraNotes {{ version }} · OraEditor {{ oraeditor }}</dd>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm dark:border-stone-800 dark:bg-stone-900 sm:col-span-2">
                <dt class="text-xs uppercase tracking-wide text-stone-400">Node en production</dt>
                <dd class="mt-1 font-medium">{{ node_runtime_required ? 'requis' : 'non requis (assets précompilés)' }}</dd>
            </div>
        </dl>
    </AdminLayout>
</template>
