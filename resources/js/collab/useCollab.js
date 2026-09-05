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
    let updateTimer = null;
    let applyingRemote = false;
    let lastEventId = 0;
    let lastSeq = 0;
    let starting = false;

    function handleOnline() {
        online.value = true;
        reconnect();
    }
    function handleOffline() {
        online.value = false;
        connected.value = false;
    }

    function applyEncoded(blob) {
        if (!blob) {
            return;
        }
        applyingRemote = true;
        applyRemoteUpdate(bundle.ydoc, blob);
        const doc = oraDocumentFromY(bundle);
        applyingRemote = false;
        if (doc) {
            onRemoteDocument?.(doc);
        }
    }

    function applyEvent(payload) {
        if (!payload || typeof payload !== 'object') {
            return;
        }
        if (payload.id) {
            lastEventId = Math.max(lastEventId, Number(payload.id) || 0);
        }
        if (payload.seq) {
            lastSeq = Math.max(lastSeq, Number(payload.seq) || 0);
        }
        if (payload.type === 'presence') {
            members.value = payload.members || [];
        }
        if (payload.type === 'update' && payload.update) {
            applyEncoded(payload.update);
        }
        if (payload.type === 'state' && payload.state) {
            applyEncoded(payload.state);
        }
    }

    async function bootstrap() {
        const { data } = await http.get(route('api.notes.collab.show', noteId));
        members.value = data.members || [];
        lastSeq = Math.max(lastSeq, Number(data.seq) || 0);
        lastEventId = Math.max(lastEventId, Number(data.snapshot_event_id) || 0);
        if (data.state) {
            applyEncoded(data.state);
        }
        (data.events || []).forEach(applyEvent);
        return data;
    }

    function connect() {
        if (source) {
            source.close();
        }
        const url = `${route('api.notes.collab.stream', noteId)}?after=${lastEventId}`;
        source = new EventSource(url, { withCredentials: true });
        source.onopen = () => { connected.value = true; };
        source.onerror = () => { connected.value = false; };
        source.addEventListener('revoked', () => {
            connected.value = false;
            onRevoked?.();
        });
        source.onmessage = (event) => {
            if (event.lastEventId) {
                lastEventId = Math.max(lastEventId, Number(event.lastEventId) || 0);
            }
            applyEvent(JSON.parse(event.data));
        };
    }

    async function reconnect() {
        if (starting) {
            return;
        }
        starting = true;
        try {
            await bootstrap();
            connect();
        } finally {
            starting = false;
        }
    }

    function postCollab(body) {
        return http.post(route('api.notes.collab.update', noteId), body)
            .then((response) => {
                const seq = Number(response.data?.seq);
                if (Number.isFinite(seq)) {
                    lastSeq = Math.max(lastSeq, seq);
                }
                return response;
            })
            .catch(() => http.post(route('api.notes.collab.update', noteId), body).catch(() => {}));
    }

    function localChange(document) {
        if (!canEdit || applyingRemote) {
            return;
        }
        applyOraDocument(bundle, document);
        clearTimeout(updateTimer);
        updateTimer = setTimeout(() => {
            postCollab({ update: encodeState(bundle.ydoc) });
        }, 200);
        clearTimeout(snapshotTimer);
        snapshotTimer = setTimeout(() => {
            postCollab({ state: encodeState(bundle.ydoc), seq: lastSeq });
        }, 4000);
    }

    async function start() {
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        await reconnect();
    }

    async function stop() {
        clearTimeout(snapshotTimer);
        clearTimeout(updateTimer);
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
