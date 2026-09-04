import { onUnmounted, ref } from 'vue';
import http from '@/composables/useHttp';
import { applyOraDocument, applyRemoteUpdate, createOraYDoc, encodeState, oraDocumentFromY } from './oraYjs';

export function useCollab(noteId, { canEdit, onRemoteDocument, onRevoked }) {
    const members = ref([]);
    const online = ref(navigator.onLine);
    const connected = ref(false);
    const transport = ref('sse+yjs');
    const bundle = createOraYDoc();
    let source = null;
    let snapshotTimer = null;
    let applyingRemote = false;

    function handleOnline() {
        online.value = true;
        connect();
    }
    function handleOffline() {
        online.value = false;
        connected.value = false;
    }

    async function bootstrap() {
        const { data } = await http.get(route('api.notes.collab.show', noteId));
        members.value = data.members || [];
        if (data.state) {
            applyRemoteUpdate(bundle.ydoc, data.state);
            const doc = oraDocumentFromY(bundle);
            if (doc) {
                onRemoteDocument?.(doc);
            }
        }
        return data;
    }

    function connect() {
        if (source) {
            source.close();
        }
        const url = route('api.notes.collab.stream', noteId);
        source = new EventSource(url, { withCredentials: true });
        source.onopen = () => { connected.value = true; };
        source.onerror = () => { connected.value = false; };
        source.addEventListener('revoked', () => {
            connected.value = false;
            onRevoked?.();
        });
        source.onmessage = (event) => {
            const payload = JSON.parse(event.data);
            if (payload.type === 'presence') {
                members.value = payload.members || [];
            }
            if (payload.type === 'update' && payload.update) {
                applyingRemote = true;
                applyRemoteUpdate(bundle.ydoc, payload.update);
                const doc = oraDocumentFromY(bundle);
                applyingRemote = false;
                if (doc) {
                    onRemoteDocument?.(doc);
                }
            }
        };
    }

    function localChange(document) {
        if (!canEdit || applyingRemote) {
            return;
        }
        applyOraDocument(bundle, document);
        const update = encodeState(bundle.ydoc);
        http.post(route('api.notes.collab.update', noteId), { update }).catch(() => {});
        clearTimeout(snapshotTimer);
        snapshotTimer = setTimeout(() => {
            http.post(route('api.notes.collab.update', noteId), { state: encodeState(bundle.ydoc) }).catch(() => {});
        }, 4000);
    }

    async function start() {
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        await bootstrap();
        connect();
    }

    async function stop() {
        clearTimeout(snapshotTimer);
        source?.close();
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
        try {
            await http.post(route('api.notes.collab.leave', noteId));
        } catch {
            // ignore
        }
    }

    onUnmounted(stop);

    return { members, online, connected, transport, localChange, start, stop, bundle };
}
