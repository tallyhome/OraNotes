# Analyse OraEditor (v0.1.4)

Source : [tallyhome/OraEditor](https://github.com/tallyhome/OraEditor) — lecture du dépôt public (README, `docs/`, `packages/`, `ready/ora-editor/`).

## Qu’est-ce qu’OraEditor

Éditeur de contenu riche **autonome et offline**. Le moteur TypeScript ne dépend d’aucun backend ni framework UI (pas de React, Vue, Laravel, CDN).

- Version actuelle : **0.1.4** (`CORE_VERSION`) — toolbar wrap dans les hôtes étroits
- Document Model : **version 1** (`DOCUMENT_MODEL_VERSION`)
- Licence du package Laravel : **MIT** (pas de fichier LICENSE racine ; notices à conserver)

## Architecture du monorepo

```
Hôte (Laravel, PHP, Node, WebView, offline)
        │
   Simple / Full   (presets, pas des forks)
        │
      Core
        │
   Document Model  ← source de vérité
        │
   Transactions → History → Renderer (DOM)
```

Principes :

1. Le **JSON Document Model** est la source de vérité. Le DOM n’est qu’une vue.
2. Toute mutation passe par une **transaction** (opérations inversibles → undo/redo natif).
3. Le Core n’importe aucun framework hôte.
4. Features fondamentales (texte, titres, images, tableaux, médias) sont natives.
5. Plugins via API publique (une dépendance max).

### Packages

| Package | Rôle |
|---|---|
| `@ora-editor/core` | Moteur (schéma, transactions, sélection, historique, commandes, rendu) |
| `@ora-editor/simple` | Preset compact (texte + images) |
| `@ora-editor/full` | Preset complet + IA |
| `@ora-editor/update-manager` | Outil hôte Node (jamais importé par le runtime navigateur) |
| `ora/laravel` | Adapter Blade / upload / proxy IA (Phase 12) |
| `@ora-editor/playground` | Démo `npm run dev` |

**Ces packages npm / Composer ne sont pas publiés** sur le registre public au moment de l’analyse (`@ora-editor/core` pointe `src/index.ts`, monorepo `private: true` ; `ora/laravel` n’a pas de Packagist).

## Document Model JSON (source de vérité)

```ts
interface OraDocument {
  version: number; // 1
  type: "doc";
  content: OraNode[];
}
```

- Blocs texte : `paragraph`, `heading`, `blockquote`, `codeBlock`, `listItem` → inline
- Blocs atomiques : `image`, `video`, `audio`, `embed`, `file`
- Tableaux : `table` → `tableRow` → `tableCell`
- Marks : `bold`, `italic`, `underline`, `strike`, `code`, sub/sup, couleurs, polices, `link`, `mention`
- Converters : `toJSON` / `fromJSON` (canonique), `toHTML` / `fromHTML` (subset sanitisé)
- Le HTML n’est **jamais** considéré comme sûr (`javascript:`, `data:`, SVG scripté, iframes hors YouTube/Vimeo refusés)

**OraNotes stocke `document` JSON en colonne JSON. Le HTML n’est qu’un dérivé (aperçu / export / recherche).**

## API publique

```js
const editor = new OraEditor({
  element: "#editor",
  content,            // OraDocument | string
  editable: true,
  toolbar: true,
  preset: "full",     // "simple" | "full"
  locale: "fr",       // fr | en | ru | pt | es | it | de
  theme: "auto",      // light | dark | auto
  placeholder: "…",
  uploadImage,        // (file, ctx) => Promise<UploadedAsset>
  uploadFile,
  openMediaLibrary,
  aiProxyUrl,
  mentions,
  plugins,
});

editor.getJSON();
editor.setJSON(doc);
editor.getHTML();
editor.setHTML(html);
editor.undo();
editor.redo();
editor.toggleTheme();
editor.setLocale("en");
editor.getStats();
editor.destroy();
editor.on("change", () => {});
```

Événements : `ready`, `change`, `focus`, `blur`, `selectionChange`, `destroy`, uploads, mentions, IA.

## Adapter Laravel (`packages/laravel/`)

- `OraEditorServiceProvider` : vues `ora`, publication `ora-assets` / `ora-views`
- Blade `<x-ora::editor>` : script tag + `new OraEditor`, option Livewire `wire-model`
- `UploadController` : stockage `public/ora-editor` (minimal — OraNotes implémentera une validation plus stricte)
- `AiProxyController` : clé API côté serveur uniquement
- **Non publié sur Packagist** → ne pas `composer require ora/laravel` aujourd’hui

## Bundle prêt (`ready/ora-editor/`)

Kit officiel « rien à compiler » :

- `ora-editor.js` (IIFE, `window.OraEditor`)
- `ora-editor.mjs` (ESM)
- `ora-editor.css`
- `ora-editor.manifest.json` (version 0.1.4, canal `stable`, checksums SHA-256)

C’est la méthode d’intégration **recommandée** tant que les packages npm/Composer ne sont pas publiés.

## Méthode d’intégration retenue pour OraNotes

Voir [architecture.md](architecture.md). En résumé :

1. Vendoriser le bundle officiel `ready/ora-editor/` (JS + CSS + manifest) dans `public/vendor/ora-editor/`.
2. Instancier `window.OraEditor` depuis Vue (mode édition uniquement).
3. Persister `getJSON()` ; dériver `getHTML()` + texte plat pour miniatures et recherche.
4. Upload images/fichiers via un contrôleur Laravel sécurisé (MIME, taille, extension, nom).
5. Ne jamais recopier le source TypeScript du Core ni réécrire un éditeur.

## Thèmes et i18n

`theme: "light" | "dark" | "auto"` — à synchroniser avec les préférences utilisateur OraNotes.

Locales natives : `fr`, `en`, `ru`, `pt`, `es`, `it`, `de`. Défaut toolbar = langue de la page.

## Hors scope OraEditor (responsabilité hôte)

- Auth, utilisateurs, partage, desktop virtuel
- Stockage physique des uploads (jamais Base64 par défaut)
- Proxy IA (clés secrètes côté serveur)
- Update Manager (job serveur, pas le navigateur)
- Dashboard admin
