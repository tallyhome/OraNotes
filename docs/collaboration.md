# Collaboration temps réel

## Architecture

- **CRDT** : [Yjs](https://yjs.dev) côté clients. Les nœuds texte OraEditor sont des `Y.Text` (fusion caractère, pas last-write-wins du JSON entier).
- **Transport** : Server-Sent Events (push) + `POST` des updates Yjs. Ce n’est **pas** un polling client du document.
- **Reverb / WebSocket** : prévu, mais `laravel/reverb` est incompatible avec Guzzle PSR-7 v3 de ce projet. Dès que la contrainte est levée, le même CRDT peut passer sur Reverb sans changer le modèle.
- **Autorisation** : Policy `view` / `update` **avant** lecture d’état, envoi d’update, et à chaque cycle SSE (révocation mid-session → événement `revoked`).
- **Présence** : cache court (nom, avatar). Lecture seule visible, édition seulement si `canEdit`.
- **Historique** : snapshots Yjs périodiques (~4 s) + versions OraNotes déjà existantes (debounce document, pas une version par frappe).
- **Offline** : brouillon `localStorage` existant + filet `online`/`offline`. À la reconnexion, l’état Yjs est rechargé puis fusionné.

## Limites assumées

Le worker PHP-FPM lit un bus d’événements en cache (pas Redis pub/sub). Ce n’est pas présenté comme un cluster WebSocket. Pour un grand nombre de pairs simultanés, installer un bus (Redis) ou Reverb lorsque les dépendances le permettent.
