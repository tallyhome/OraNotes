# OraNotes

Application web de prise de notes **spatiale** : un bureau virtuel de Post-it. Chaque note est un document [OraEditor](https://github.com/tallyhome/OraEditor) complet.

> Version : **1.0.0**

## À propos

OraNotes n’est pas une liste de notes. L’organisation spatiale (position, taille, couleur, superposition) est l’expérience principale. Blend : bureau virtuel + tableau blanc + Post-it + notes modernes + partage.

## Intégration OraEditor

**On n’a pas réinventé d’éditeur riche.** OraEditor **0.1.3** est le moteur.

| Point | Choix |
|---|---|
| Source de vérité | Document Model JSON (`getJSON` / `setJSON`) |
| HTML | Dérivé (`getHTML`) pour miniatures, export et recherche |
| Méthode | Kit officiel `ready/ora-editor/` vendorisé dans `public/vendor/ora-editor/` |
| Pourquoi pas npm / Composer | `@ora-editor/*` et `ora/laravel` ne sont pas publiés |
| Instanciation | `new window.OraEditor()` uniquement en mode édition (modal) |
| Miniatures | Aperçu HTML sanitisé, **zéro** instance OraEditor sur le canvas |
| Upload | Contrôleur Laravel (MIME réel, taille, extension, CSRF) |
| Thème | `light` / `dark` / `auto`, aligné sur les préférences utilisateur |

Détail : [docs/ora-editor-analysis.md](docs/ora-editor-analysis.md) · [docs/architecture.md](docs/architecture.md) · [public/vendor/ora-editor/NOTICE](public/vendor/ora-editor/NOTICE)

## Stack

- PHP 8.3+ · **Laravel 13**
- Vue 3 · Inertia.js · Vite 8 · Tailwind CSS 3
- SQLite (local / tests) · MySQL 8 / MariaDB (production)
- OraEditor 0.1.3 (bundle `ready/`)

## Prérequis

- PHP 8.3+ avec extensions : `mbstring`, `xml`, `curl`, `sqlite3` (ou `pdo_mysql`), `zip`, `gd`, `bcmath`, `intl`
- Composer 2
- Node.js 20+ et npm
- SQLite (dev) ou MySQL / MariaDB (prod)

## Installation

```bash
git clone https://github.com/tallyhome/OraNotes.git
cd OraNotes
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # si SQLite
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Ouvrir `http://localhost:8000`. En développement frontend : `npm run dev` en parallèle de `php artisan serve`.

### MySQL / MariaDB

Dans `.env` :

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oranotes
DB_USERNAME=oranotes
DB_PASSWORD=…
```

Puis `php artisan migrate --seed`.

### Créer un administrateur

```bash
php artisan oranotes:create-admin admin@example.com "Ada Admin" --password="un-mot-de-passe-fort"
```

## Comptes de démo (seeder)

**Mots de passe de démo uniquement — pas des secrets.** Tous : `password`

| Rôle | Email |
|---|---|
| Admin | `admin@oranotes.test` |
| Utilisatrice | `alice@oranotes.test` |
| Utilisateur | `bob@oranotes.test` |
| Utilisatrice | `clara@oranotes.test` |

Alice possède plusieurs bureaux, ~24 notes, tags, favoris et partages vers Bob (édition) et Clara (lecture).

## Tests

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

La suite inclut l’auth Breeze, le CRUD workspaces/notes, la recherche, et des **tests de sécurité IDOR / partage / comptes désactivés / admin**.

## Fonctionnalités V1

- Auth : inscription, connexion, reset mot de passe, vérification e-mail, profil, avatar, thème clair/sombre/auto
- Rôles `user` / `admin`, comptes activables
- Workspaces (bureaux) : créer, renommer, archiver, dupliquer, icône, couleur, défaut, membres
- Bureau virtuel : drag, resize, multi-sélection, zoom, pan, pinch, grille, aligner, z-index, verrou
- Notes : titre, JSON OraEditor, couleur, statut, priorité, tags, favori, archive, corbeille
- Autosave contenu (debounce) + positions (throttle) + brouillon `localStorage`
- Partage utilisateur (lecture / édition) et lien tokenisé (lecture, expiration, révocation)
- Recherche globale + palette ⌘/Ctrl+K
- Notifications in-app, journal d’activité
- Admin : stats, utilisateurs, notes, bureaux, journal
- Export JSON / HTML
- Uploads sécurisés pour OraEditor
- Responsive + raccourcis (N, Delete, D, ⌘+/−/0, C/V)

## API (session + CSRF)

Préfixe `/api/…` (middleware web authentifié), identifiants publics = **UUID** (pas d’IDs internes dans les URLs).

Exemples : `POST /api/workspaces/{uuid}/notes`, `PATCH /api/workspaces/{uuid}/positions`, `GET /api/search?q=`, `POST /api/uploads`.

Lien public : `/s/{token}`.

## Déploiement

1. PHP 8.3, Composer, Node pour le build d’assets
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `.env` production (`APP_DEBUG=false`, `APP_URL`, MySQL, `FILESYSTEM_DISK=public`)
5. `php artisan migrate --force`
6. `php artisan storage:link`
7. `php artisan config:cache && php artisan route:cache`
8. Document root = `public/`

## Licence

MIT. OraEditor (bundle vendorisé) : MIT — voir `public/vendor/ora-editor/NOTICE`.
