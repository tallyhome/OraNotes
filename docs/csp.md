# Content-Security-Policy (OraNotes)

## Stratégie

CSP est **Report-Only** par défaut (`CSP_REPORT_ONLY=true`). Cela permet d’observer les violations sans casser Laravel / Inertia / Vite / Ziggy / OraEditor / uploads.

Passer en application stricte uniquement après revue des rapports :

```
CSP_ENABLED=true
CSP_REPORT_ONLY=false
CSP_REPORT_URI=/csp-report   # optionnel
```

En local (`APP_ENV=local`) la politique reste large pour le HMR Vite (`localhost:5173`, `ws:`).

## Politique (production)

| Directive | Valeur | Pourquoi |
|---|---|---|
| `default-src` | `'self'` | Base |
| `script-src` | `'self' 'unsafe-inline'` | Ziggy `@routes` + bootstrap Inertia (scripts inline) |
| `style-src` | `'self' 'unsafe-inline' https://fonts.bunny.net` | Tailwind, styles OraEditor, polices |
| `font-src` | `'self' https://fonts.bunny.net data:` | Outfit / Source Serif |
| `img-src` | `'self' data: blob: https:` | Miniatures, uploads, images distantes sanitisées |
| `connect-src` | `'self' ws: wss:` | API + WebSocket collab (Reverb) |
| `frame-src` | `'self' youtube / vimeo` | Embeds OraEditor |
| `media-src` | `'self' blob:` | Audio / vidéo notes |
| `object-src` | `'none'` | Pas de Flash / plugins |
| `base-uri` | `'self'` | Anti `<base>` hijack |
| `form-action` | `'self'` | Formulaires same-origin |
| `frame-ancestors` | `'self'` | Clickjacking (complète `X-Frame-Options`) |

## Exceptions assumées

- **`unsafe-inline` scripts** : Ziggy génère un `<script>` inline ; Inertia peut injecter du head. Un nonce unique par requête est préparé (`$cspNonce`) pour une migration future. Tant que Ziggy n’est pas exporté en fichier, `unsafe-inline` reste nécessaire.
- **`unsafe-inline` styles** : OraEditor et Tailwind posent des styles inline (positions canvas, thèmes). `unsafe-eval` n’est **pas** ajouté : le bundle Vite de production et l’IIFE OraEditor 0.1.3 n’en ont pas besoin.
- **`img-src https:`** : les URLs d’images dans le Document Model sont déjà filtrées côté serveur (`HtmlSanitizer::isSafeUrl`).

## Ce qui n’est pas simulé

Aucune politique « tout ouvert » n’est présentée comme CSP stricte. Le mode enforce est un choix d’exploitant, pas le défaut.
