# Changelog

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
