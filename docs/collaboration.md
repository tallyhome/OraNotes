# Collaboration temps réel

## Architecture

- **CRDT** : [Yjs](https://yjs.dev) côté clients. Les nœuds texte OraEditor sont des `Y.Text` (fusion caractère, pas last-write-wins du JSON entier).
- **Transport** : Server-Sent Events (push) + `POST` des updates Yjs. Ce n’est **pas** un polling client du document.
- **Journal durable** : chaque update / snapshot / présence est un `INSERT` atomique dans `collab_events` (auto-incrément). Ce n’est **pas** un `Cache::get` + `Cache::put`.
- **Snapshot** : checkpoint Yjs sur `notes.collab_state`, protégé par `lockForUpdate`. Un snapshot périmé (`seq` client &lt; `collab_seq`) **n’écrase pas** le checkpoint ; ses octets sont tout de même ajoutés au journal pour fusion Yjs.
- **Bootstrap** : snapshot + événements `id > collab_snapshot_event_id`. Deux clients frais doivent reconstruire le même état.
- **SSE** : curseur `?after=` **ou** en-tête `Last-Event-ID` (le plus grand des deux). La fin du cycle 25 s ne retire pas la présence (seul `POST .../leave` ou TTL 2 min).
- **Reverb / WebSocket** : prévu, mais `laravel/reverb` est incompatible avec Guzzle PSR-7 v3 de ce projet. Dès que la contrainte est levée, le même CRDT peut passer sur Reverb sans changer le modèle.
- **Autorisation** : Policy `view` / `update` **avant** lecture d’état, envoi d’update, et à chaque cycle SSE (révocation mid-session → événement `revoked`).
- **Présence** : cache court derrière `Cache::lock` (nom, avatar, TTL 2 min).
- **Hors-ligne** : brouillon `localStorage` + filet `online`/`offline`. À la reconnexion le client **recharge le snapshot puis le journal**, puis rouvre le flux.

## Déploiement

| Topologie | Verdict |
|---|---|
| Un serveur, un worker PHP | Correct si la base est partagée (SQLite fichier ou MySQL). |
| Un serveur, plusieurs workers (php-fpm / Octane) | Correct : le journal et les snapshots sont en base. La présence passe par le cache **partagé** (`CACHE_STORE=database` ou Redis). |
| Plusieurs serveurs | Correct **uniquement** si tous partagent la **même** base (événements + snapshots) et un cache **partagé** (database/Redis) pour la présence. `CACHE_STORE=array` ou un cache fichier local à chaque nœud casse la présence (pas le document). |
| SSE « horizontalement scalable » sans store partagé | **Non.** SSE n’est pas un bus pub/sub. Chaque worker lit le journal en base ; il n’y a pas de fan-out Redis. Un sticky session n’est pas requis pour la correction, mais 50 flux SSE occupent 50 workers PHP. |

SQLite tient la correction (inserts atomiques, `lockForUpdate`, `busy_timeout` 5 s, WAL) mais **pas** une charge élevée : les flux SSE relisent la note toutes les 400 ms et le rate-limiter écrit dans la table `cache`. Pour 10+ pairs simultanés, utiliser MySQL/MariaDB (ou Redis plus tard).

## Limites assumées

- Pas de merge Yjs côté PHP : le serveur ne comprend pas le CRDT. Il garantit l’ordre et la durabilité du journal ; la fusion a lieu sur les clients.
- Le bus n’est pas Redis pub/sub. Pour un très grand nombre de pairs, installer un bus (Redis) ou Reverb lorsque les dépendances le permettent.
- `php artisan serve` (serveur PHP mono-thread) ne convient pas aux flux SSE concurrentiels.
