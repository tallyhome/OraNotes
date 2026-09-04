import * as Y from 'yjs';

function pathKey(path) {
    return path.join('.');
}

function collectText(node, path, acc) {
    if (node?.type === 'text') {
        acc.set(pathKey(path), typeof node.text === 'string' ? node.text : '');
        return;
    }
    (node?.content || []).forEach((child, index) => collectText(child, [...path, index], acc));
}

function applyTextMap(node, path, texts) {
    if (node?.type === 'text') {
        const next = texts.get(pathKey(path));
        if (typeof next === 'string') {
            node.text = next;
        }
        return node;
    }
    if (Array.isArray(node?.content)) {
        node.content = node.content.map((child, index) => applyTextMap(child, [...path, index], texts));
    }
    return node;
}

function syncYText(ytext, next) {
    const current = ytext.toString();
    if (current === next) {
        return;
    }
    let prefix = 0;
    while (prefix < current.length && prefix < next.length && current[prefix] === next[prefix]) {
        prefix++;
    }
    let suffix = 0;
    while (
        suffix < current.length - prefix
        && suffix < next.length - prefix
        && current[current.length - 1 - suffix] === next[next.length - 1 - suffix]
    ) {
        suffix++;
    }
    const deleteCount = current.length - prefix - suffix;
    if (deleteCount > 0) {
        ytext.delete(prefix, deleteCount);
    }
    const insert = next.slice(prefix, next.length - suffix);
    if (insert) {
        ytext.insert(prefix, insert);
    }
}

export function createOraYDoc() {
    const ydoc = new Y.Doc();
    const texts = ydoc.getMap('texts');
    const structure = ydoc.getMap('structure');
    return { ydoc, texts, structure };
}

export function applyOraDocument(bundle, document) {
    const texts = new Map();
    collectText(document, [], texts);
    bundle.ydoc.transact(() => {
        bundle.structure.set('doc', JSON.parse(JSON.stringify(document)));
        texts.forEach((value, key) => {
            let ytext = bundle.texts.get(key);
            if (!(ytext instanceof Y.Text)) {
                ytext = new Y.Text();
                bundle.texts.set(key, ytext);
            }
            syncYText(ytext, value);
        });
        [...bundle.texts.keys()].forEach((key) => {
            if (!texts.has(key)) {
                bundle.texts.delete(key);
            }
        });
    });
}

export function oraDocumentFromY(bundle) {
    const base = bundle.structure.get('doc');
    if (!base) {
        return null;
    }
    const clone = JSON.parse(JSON.stringify(base));
    const map = new Map();
    bundle.texts.forEach((ytext, key) => {
        map.set(key, ytext instanceof Y.Text ? ytext.toString() : '');
    });
    return applyTextMap(clone, [], map);
}

export function encodeState(ydoc) {
    const bytes = Y.encodeStateAsUpdate(ydoc);
    let binary = '';
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
        binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
    }
    return btoa(binary);
}

export function applyRemoteUpdate(ydoc, b64) {
    const raw = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
    Y.applyUpdate(ydoc, raw);
}

export { Y };
