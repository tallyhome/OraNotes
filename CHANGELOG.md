# Changelog

## 1.2.1 — 2026-09-05

Remplacement des popups natifs du navigateur par SweetAlert2.

- Helper partagé `resources/js/lib/swal.js` (toast, succès, erreur, info, confirm, prompt)
- Thème papier / stone / orange, boutons FR (Annuler / Confirmer / OK), dark mode
- Accueil et bureau : création de bureau (prompt)
- Bureau : archivage, verrouillage / déverrouillage, corbeille, alerte si verrouillé
- Corbeille : purge note, purge bureau (saisie du nom si notes)
- Admin : purge bureaux (saisie du nom), verrouillage, purge notes, corbeille utilisateur, application des mises à jour

## 1.2.0 — 2026-09-05

Refonte UX de l’administration et correction de la page Bureaux vide.

- Cause : le paginateur Inertia `workspaces` écrasait le prop partagé (tableau de bureaux). `CommandPalette` appelait `.forEach` sur l’objet paginateur et faisait planter la page.
- Correctif : `navWorkspaces` dédié à la navigation, itération de `workspaces.data`, résolution des actions par UUID (y compris corbeille).
- Shell admin autonome (sidebar groupée, état actif, mobile, retour à l’application)
- Tableaux, filtres, états vides, pagination et libellés français sur toutes les pages admin
- Mises à jour : erreur SSL/cURL 60 (bundle CA manquant, fréquent sous Windows) affichée en français avec marche à suivre ; `ORANOTES_CA_BUNDLE` / `composer/ca-bundle` ; jamais `verify => false`

## 1.1.1 — 2026-09-05

Durabilité et concurrence de la collaboration (SSE + Yjs).

- Journal `collab_events` en base (insert atomique) à la place de `Cache::get`/`put`
- Snapshot protégé par `lockForUpdate` : un checkpoint périmé n’écrase plus l’état
- Les snapshots rejetés restent dans le journal pour fusion Yjs
- SSE honore `Last-Event-ID` et `?after=` ; fin de cycle sans `leave()` implicite
- Bootstrap = snapshot + queue d’updates ; reconnexion client recharge puis fusionne
- Présence sous `Cache::lock` + TTL ; throttle d’écriture collab relevé (240/min)
- Tests de régression concurrence / reconstruction ; docs d’architecture honnêtes

## 1.1.0 — 2026-09-04

Fonctionnalités et durcissement depuis 1.0.3.

- Permissions archive / corbeille / partages cohérentes (UUID ≠ accès)
- Validation renforcée du Document Model OraEditor
- Réduction des fuites d’énumération de comptes
- CSP Report-Only (sans `unsafe-eval`)
- Workspaces : lock, trash, restore, force-delete confirmé
- Grille canvas réelle (overlay + snap + zoom/pan + persistance)
- Alignement / distribution complets, espace canvas extensible
- Admin étendu (dashboard, users, workspaces, notes, activity, system, updates, settings, security, storage, health)
- Auto-update GitHub Releases uniquement + backup / rollback best-effort
- Assistant web `/install` verrouillé après install
- Collaboration Yjs + SSE autorisé, présence, notifications collab
- Recherche FULLTEXT MySQL / LIKE ailleurs
- Docs HTML `Doc/`, scripts d’exploitation, kit `promo/`
- Tests PHPUnit étendus + E2E Playwright

## 1.0.3

Baseline production-dev (partages, attachments, version bump).
