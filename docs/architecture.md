# Architecture OraNotes v1

Décision enregistrée le 2026-09-04. Voir aussi [ora-editor-analysis.md](ora-editor-analysis.md).

## Produit

OraNotes est une application web de prise de notes **spatiale** : un bureau virtuel de Post-it librement positionnés. Chaque note est un document OraEditor complet (JSON Document Model). Ce n’est pas une liste de notes.

Mélange : bureau virtuel + tableau blanc + Post-it + notes modernes + espace collaboratif (partage).

## Stack

| Couche | Choix | Pourquoi |
|---|---|---|
| Backend | **Laravel 13** + PHP 8.3+ | Version courante, Policies, Form Requests |
| Auth | Laravel Breeze (Inertia) | Register / login / reset / verify / profil |
| Frontend | **Vue 3 + Inertia.js + Vite** | SPA-like pour le canvas, pages Laravel, pas de double API REST obligatoire |
| Éditeur | **OraEditor 0.1.3** bundle `ready/` | Officiel, rien à compiler, Core indépendant |
| CSS | CSS custom + tokens (clair/sombre) | Look Post-it, pas un CRUD générique |
| DB locale / tests | **SQLite** | Simple, documenté, CI |
| DB prod | MySQL 8 / MariaDB | Documenté dans `.env.example` |
| Temps réel | **Non en V1** | Architecture prête (events, versions) sans CRDT |

### Pourquoi Vue + Inertia plutôt que Blade + Alpine

Le bureau virtuel (drag, resize, multi-select, zoom, pan, 100+ notes) est un moteur d’interaction. Vue 3 le gère proprement. OraEditor reste **vanilla** : une instance `new OraEditor()` uniquement en mode édition, jamais recréée à chaque `change`.

Le package `ora/laravel` (Blade/Livewire) n’est pas sur Packagist et cible un formulaire page, pas un canvas. On s’aligne sur son contrat (JSON, upload CSRF, destroy) sans l’embarquer.

## Intégration OraEditor

**Méthode :** vendoriser le kit officiel `ready/ora-editor/` (JS IIFE + CSS + manifest) dans `public/vendor/ora-editor/`.

1. Charger `ora-editor.css` + `ora-editor.js` une seule fois (layout Inertia).
2. Composant Vue `OraEditorHost` : monte `new window.OraEditor({ element, content, preset, locale, theme, uploadImage })`.
3. Source de vérité persistée : `editor.getJSON()` → colonne `notes.document` (JSON).
4. Dérivés à la sauvegarde : `html_preview` (`getHTML()`), `text_content` (texte plat pour recherche).
5. `editor.on('change')` → debounce autosave (pas un HTTP par frappe).
6. `editor.destroy()` au unmount (modal fermée / navigation).
7. Miniatures desktop : HTML sanitisé stocké, **pas** d’instance OraEditor par note.
8. Undo/redo natifs de l’éditeur en mode édition ; undo spatial (positions) côté canvas.
9. Notices MIT / provenance conservées dans `public/vendor/ora-editor/NOTICE`.

Ne pas : copier le source TypeScript du Core, réécrire un éditeur, stocker le HTML comme source de vérité, instancier N éditeurs sur le canvas.

## Séparation des responsabilités

```
HTTP / Inertia
  Controllers (minces) + Form Requests
        │
  Policies / Gates          Services
        │                      │
  Eloquent models  ←→  Activity, Sharing, Search, Desktop
        │
  MySQL / SQLite
```

- **Auth / Users / Roles** — `user` | `admin`, compte actif, préférences (thème, locale).
- **Workspaces** — un desktop par workspace ; owner + membres.
- **Notes** — géométrie + document JSON + métadonnées.
- **Sharing** — user-to-user + lien tokenisé (pas d’ID interne dans l’URL publique).
- **Virtual desktop engine** — 100 % frontend (transform CSS, hit-testing, group ops) + API positions debounce.
- **OraEditor host** — pont Vue ↔ Core.
- **Admin** — stats, utilisateurs, modération.
- **Activity + Notifications** — journal extensible, cloche in-app.

## Identifiants publics

Les routes utilisateur exposent des **UUID** (`notes.uuid`, `workspaces.uuid`, `share_links.token`). Les IDs auto-incrémentés restent internes. Réduit l’énumération / IDOR trivial.

## Autorisation

Toute lecture/écriture passe par une Policy :

- Owner workspace / note
- Membre workspace (`read` | `edit`)
- Share user (`read` | `edit`)
- Share link (lecture, token + expiry)
- Admin (modération, pas propriétaire silencieux)

Les tests de sécurité vérifient qu’un utilisateur A ne peut jamais accéder à une note de B en changeant un UUID ou un ID.

## Autosave & brouillons

- Positions : throttle ~400 ms, batch `PATCH /notes/positions`.
- Contenu : debounce ~800 ms, `PUT` document JSON.
- UI : `Saving…` / `Saved` / `Error`.
- Échec réseau : brouillon `localStorage` (`oranotes:draft:{noteUuid}`), retry, jamais de perte silencieuse.

## Temps réel (1.1)

- **CRDT** Yjs côté clients (textes OraEditor). Pas de last-write-wins du JSON entier.
- **Transport** SSE + POST d’updates, Policy avant souscription et à chaque cycle. Révocation → `event: revoked`.
- Reverb / WebSocket : non livré (Guzzle PSR-7 v3). Voir `docs/collaboration.md`.

## Performance canvas

- Une couche transform (`translate` + `scale`) pour pan/zoom.
- Notes = nœuds Vue légers (pas d’éditeur).
- Pas de re-render global à chaque pixel : mutations locales + RAF.
- Indexes SQL sur `workspace_id`, `user_id`, `deleted_at`, `uuid`, full-text / LIKE sur `title` + `text_content`.

## Sécurité (non négociable)

CSRF (Inertia), XSS (JSON + HTML sanitisé OraEditor + `{!! !!}` interdit sur HTML brut non filtré), mass assignment (`$fillable`), Policies, rate limit login/share/upload, validation MIME réelle, tokens de partage aléatoires, sessions Laravel, comptes désactivables.
