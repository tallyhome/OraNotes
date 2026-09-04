<script setup>
import { Head } from '@inertiajs/vue3';
import OraEditorHost from '@/Components/Editor/OraEditorHost.vue';
defineProps({ note: Object, readOnly: Boolean });
</script>
<template>
    <Head :title="note.title" />
    <div class="min-h-screen bg-paper-50 px-4 py-10">
        <div class="mx-auto max-w-3xl rounded-2xl bg-white p-6 shadow-lg">
            <p class="text-xs uppercase text-stone-400">Lien partagé · lecture seule</p>
            <h1 class="mb-4 text-2xl font-semibold">{{ note.title }}</h1>
            <OraEditorHost :content="note.document" :editable="false" />
        </div>
    </div>
</template>
