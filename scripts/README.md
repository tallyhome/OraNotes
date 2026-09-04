# Scripts d’exploitation

| Fichier | Rôle |
|---|---|
| `package-dist.sh` | Build Composer --no-dev + `npm ci && npm run build` + zip **avec** `public/build`, **sans** `.env` / `node_modules` |
| `apache-vhost.conf` | Vhost Apache, root = `public/` |
| `nginx-server.conf` | Server Nginx + hint SSE |
| `iis-web.config` | Rewrite IIS |
| `windows-iis.ps1` | Détection PHP + commandes de droits (n’écrit pas dans IIS) |
| `generate-html-docs.py` | Régénère `Doc/*.html` |

PHP ne peut pas créer un site IIS/cPanel/Plesk à votre place : les pages `Doc/` donnent le chemin exact à éditer.
