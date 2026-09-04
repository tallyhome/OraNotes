# OraNotes

Application web de prise de notes **spatiale** : un bureau virtuel de Post-it. Chaque note est un document [OraEditor](https://github.com/tallyhome/OraEditor) complet.

> Version actuelle : **0.0.1** — scaffolding, analyse OraEditor, décision d’architecture.

## À propos

OraNotes n’est pas une liste de notes. L’organisation spatiale (position, taille, couleur, superposition) est l’expérience principale. Blend : bureau virtuel + tableau blanc + Post-it + notes modernes + partage.

## Intégration OraEditor (décision)

OraEditor **0.1.3** est le moteur d’édition. On ne réinvente pas d’éditeur riche.

- **Source de vérité :** Document Model JSON (`getJSON` / `setJSON`), pas le HTML.
- **Méthode V1 :** vendoriser le kit officiel `ready/ora-editor/` (JS + CSS + manifest, rien à compiler). Les packages npm `@ora-editor/*` et Composer `ora/laravel` ne sont pas publiés.
- **Hôte :** Vue 3 instancie `new OraEditor()` uniquement en mode édition ; les miniatures du bureau utilisent un aperçu HTML dérivé.
- Détail : [docs/ora-editor-analysis.md](docs/ora-editor-analysis.md) · [docs/architecture.md](docs/architecture.md)

## Stack (retenue)

- **Backend :** Laravel 12, PHP 8.3+, Policies, Form Requests
- **Frontend :** Vue 3 + Inertia.js + Vite
- **Base :** SQLite (local / tests) · MySQL / MariaDB (production)
- **Éditeur :** OraEditor 0.1.3 (`ready/ora-editor/`)

## Feuille de route

| Tag | Objectif |
|---|---|
| v0.0.1 | Scaffolding, analyse, architecture |
| v0.1.0 | Laravel, auth, schéma / migrations |
| v0.2.0 | Workspaces + Notes CRUD + policies |
| v0.3.0 | Bureau virtuel (drag / resize / zoom / pan) |
| v0.4.0 | OraEditor + autosave |
| v0.5.0 | Tags, statuts, favoris, archive / corbeille |
| v0.6.0 | Partage, permissions, notifications, journal |
| v0.7.0 | Recherche, palette de commandes, raccourcis |
| v0.8.0 | Administration |
| v0.9.0 | Polish UX, thème, seeders, docs |
| v1.0.0 | Tests verts, build production, QA |

Les instructions d’installation détaillées arriveront dès v0.1.0.

## Licence

OraNotes : MIT (à confirmer avec le dépôt). OraEditor (bundle vendorisé) : MIT — notices conservées.
