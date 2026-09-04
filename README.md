# OraNotes

Application web de prise de notes **spatiale** : un bureau virtuel de Post-it. Chaque note est un document [OraEditor](https://github.com/tallyhome/OraEditor) complet.

> Version : **1.1.0**

## À propos

OraNotes n’est pas une liste de notes. L’organisation spatiale (position, taille, couleur, superposition) est l’expérience principale. Blend : bureau virtuel + tableau blanc + Post-it + notes modernes + partage + collaboration CRDT.

## Intégration OraEditor

**On n’a pas réinventé d’éditeur riche.** OraEditor **0.1.3** est le moteur.

| Point | Choix |
|---|---|
| Source de vérité | Document Model JSON (`getJSON` / `setJSON`) |
| HTML | Dérivé (`getHTML`) pour miniatures, export et recherche |
| Méthode | Kit officiel `ready/ora-editor/` vendorisé dans `public/vendor/ora-editor/` |
| Collab | Yjs fusionne les nœuds texte ; le serveur autorise puis relaie |
| Upload | Contrôleur Laravel (MIME réel, taille, extension, CSRF) |

Détail : [docs/ora-editor-analysis.md](docs/ora-editor-analysis.md) · [docs/architecture.md](docs/architecture.md) · [docs/collaboration.md](docs/collaboration.md)

## Stack

- PHP 8.3+ · **Laravel 13**
- Vue 3 · Inertia.js · Vite 8 · Tailwind CSS 3 · Yjs 13
- SQLite (local / tests) · MySQL 8 / MariaDB (production)
- OraEditor 0.1.3 (bundle `ready/`)

## Prérequis

- PHP 8.3+ : `mbstring`, `xml`, `curl`, `sqlite3` ou `pdo_mysql`, `zip`, `gd`, `bcmath`, `intl`
- Composer 2
- Node.js 20+ **uniquement pour compiler** les assets (`npm run build`)
- En production, un paquet distributable inclut `public/build` : **Node n’est pas requis à l’exécution**

## Installation

### Assistant web (classique)

1. Déposer le code (ou le zip `scripts/package-dist.sh`)
2. Document root = `public/`
3. Ouvrir `/install` si l’instance n’est pas déjà installée
4. Contrôles système → base (test PDO réel) → config → compte admin → migrations

Le wizard se **verrouille** après succès (`storage/app/installed.lock`). Il refuse de réinstaller une instance existante. Documentation : [Doc/installer.html](Doc/installer.html).

### Ligne de commande (dev)

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

Guides par plateforme : [Doc/index.html](Doc/index.html) (Linux, Windows, Apache, Nginx, IIS, cPanel, Webuzo, Plesk, MySQL, MariaDB).

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

## Fonctionnalités 1.1

- Auth, rôles `user` / `admin`, comptes activables
- Workspaces : créer, renommer, icône, couleur, défaut, dupliquer, archiver, restaurer, **suppression définitive confirmée**, **verrou**
- Canvas : drag, resize, multi-sélection, zoom, pan, pinch, **grille réelle**, snap, aligner / répartir, z-index, verrou note, espace extensible
- Notes OraEditor, autosave, brouillon local, favori, archive, corbeille
- Partage user (lecture / édition) et lien tokenisé
- **Collaboration Yjs + SSE** (présence, hors-ligne, révocation mid-session)
- Recherche LIKE + FULLTEXT MySQL
- Notifications (partage, modification, rejoint, accès révoqué, invitation ouverte)
- Admin : dashboard, users, bureaux, notes, activité, système, updates, settings, security, storage, health
- Auto-update **GitHub Releases uniquement** (intégrité, backup, rollback best-effort — pas atomique)
- Export JSON / HTML, uploads sécurisés
- Journal d’audit admin (sans secrets)

## Admin

`/admin` (rôle admin). Suppression utilisateurs : [docs/user-deletion.md](docs/user-deletion.md). Santé : `/admin/health`. Mises à jour : `/admin/updates` — jamais d’URL arbitraire.

## Collaboration

Voir [docs/collaboration.md](docs/collaboration.md). Autorisation Policy **avant** l’état et le flux SSE. Un lecteur ne pousse pas d’updates.

## Auto-update

Source : `api.github.com` + `ORANOTES_UPDATE_REPO` (défaut `tallyhome/OraNotes`). Hash, anti-downgrade, anti-traversal, skip `.env` / `storage`. Rollback fichiers + SQLite si présent, **non garanti atomique**.

## Sécurité

Policies, UUID publics, anti-énumération login, CSP Report-Only, limites OraEditor, uploads MIME. Détail : [Doc/security.html](Doc/security.html), [docs/csp.md](docs/csp.md).

## Tests

```bash
php artisan test
vendor/bin/pint --test
npm run build
npx playwright install chromium
npm run e2e
```

PHPUnit : auth, workspaces/lock, canvas positions, admin, install lock, update security, collab, IDOR, perf 100/250 notes. E2E Chromium : login, grille, lock, OraEditor, admin, responsive.

## Distribution

```bash
bash scripts/package-dist.sh
```

Produit `dist/oranotes-VERSION.zip` avec `vendor` (no-dev) et `public/build`, sans `.env` ni `node_modules`.

## Promo & docs

- [Doc/](Doc/index.html) — HTML d’installation
- [promo/](promo/README.md) — visuels marketplace / social / branding (originaux)
- [CHANGELOG.md](CHANGELOG.md)

## Roadmap

Reverb lorsque Guzzle PSR-7 le permet ; CSP enforce après rapport ; rollback update plus large.

## Licence

MIT. OraEditor (bundle vendorisé) : MIT — voir `public/vendor/ora-editor/NOTICE`.
