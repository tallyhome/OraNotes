<script setup>
import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import http from '@/composables/useHttp';

const open = ref(false);
const items = ref([]);
const unread = ref(usePage().props.unreadNotifications || 0);

async function load() {
    const { data } = await http.get(route('api.notifications.index'));
    items.value = data.notifications;
}

async function readAll() {
    await http.post(route('api.notifications.readAll'));
    unread.value = 0;
    items.value = items.value.map((item) => ({ ...item, read_at: new Date().toISOString() }));
}

onMounted(load);
</script>

<template>
    <div class="relative">
        <button type="button" class="relative rounded-full p-2 hover:bg-stone-100 dark:hover:bg-stone-800" @click="open = !open">
            <span>🔔</span>
            <span v-if="unread" class="absolute right-1 top-1 h-2 w-2 rounded-full bg-orange-600" />
        </button>
        <div v-if="open" class="absolute right-0 mt-2 w-80 rounded-xl border border-stone-200 bg-white p-2 text-sm shadow-xl dark:border-stone-700 dark:bg-stone-900">
            <div class="mb-2 flex items-center justify-between px-2">
                <strong>Notifications</strong>
                <button type="button" class="text-xs text-orange-700" @click="readAll">Tout lu</button>
            </div>
            <p v-if="!items.length" class="px-2 py-4 text-stone-400">Rien pour le moment.</p>
            <div v-for="item in items" :key="item.id" class="rounded-lg px-2 py-2" :class="item.read_at ? 'opacity-60' : 'bg-orange-50 dark:bg-stone-800'">
                {{ item.data.message }}
            </div>
        </div>
    </div>
</template>
