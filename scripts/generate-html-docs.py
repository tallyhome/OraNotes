#!/usr/bin/env python3
"""Génère les pages HTML de Doc/ (documentation d'installation)."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DOC = ROOT / "Doc"

NAV = [
    ("index.html", "Accueil"),
    ("linux.html", "Linux"),
    ("windows.html", "Windows"),
    ("apache.html", "Apache"),
    ("nginx.html", "Nginx"),
    ("iis.html", "IIS"),
    ("cpanel.html", "cPanel"),
    ("webuzo.html", "Webuzo"),
    ("plesk.html", "Plesk"),
    ("mysql.html", "MySQL"),
    ("mariadb.html", "MariaDB"),
    ("installer.html", "Assistant web"),
    ("updates.html", "Mises à jour"),
    ("collaboration.html", "Collaboration"),
    ("security.html", "Sécurité"),
    ("troubleshooting.html", "Dépannage"),
]


def page(title: str, body: str) -> str:
    links = "\n".join(f'<a href="{href}">{label}</a>' for href, label in NAV)
    return f"""<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{title} — OraNotes</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="wrap">
    <header class="site">
      <a href="index.html"><strong>OraNotes</strong> · documentation</a>
      <span class="muted">v1.1.0</span>
    </header>
    <nav class="toc">{links}</nav>
    {body}
    <footer>OraNotes — MIT. Les schémas sont des schémas, pas des captures de serveurs réels.</footer>
  </div>
</body>
</html>
"""


PAGES = {
    "index.html": (
        "Documentation",
        """
<h1>Installer et exploiter OraNotes</h1>
<p>OraNotes est une application Laravel 13 + Vue/Inertia : un bureau virtuel de notes OraEditor.</p>
<div class="note">En production, <strong>Node n’est pas requis à l’exécution</strong> si les assets sont déjà compilés (<code>public/build</code> dans le paquet distributable). Node sert uniquement au build.</div>
<figure class="schema">
<pre>Navigateur  →  public/ (Vite + OraEditor)
                 ↓
              Laravel 13 (Policies, AccessService)
                 ↓
         SQLite | MySQL | MariaDB
                 ↓
     Collab : Yjs clients + SSE autorisé</pre>
<figcaption>Schéma — flux de requête OraNotes 1.1.0</figcaption>
</figure>
<h2>Parcours recommandé</h2>
<ol>
  <li>Vérifier PHP 8.3+ et les extensions (voir Linux / Windows).</li>
  <li>Soit l’<a href="installer.html">assistant web</a> <code>/install</code>, soit Composer + Artisan.</li>
  <li>Pointer le vhost vers <code>public/</code> (Apache, Nginx, IIS, panneau).</li>
  <li>Créer un admin : <code>php artisan oranotes:create-admin …</code> ou via le wizard.</li>
</ol>
<h2>Liens</h2>
<ul>
  <li>Code : <a href="https://github.com/tallyhome/OraNotes">github.com/tallyhome/OraNotes</a></li>
  <li>Promo : <a href="../promo/README.md">../promo/</a></li>
  <li>Architecture markdown : <a href="../docs/architecture.md">../docs/architecture.md</a></li>
</ul>
""",
    ),
    "linux.html": (
        "Linux",
        """
<h1>Linux (Debian / Ubuntu / RHEL)</h1>
<h2>Paquets PHP 8.3</h2>
<pre>sudo apt update
sudo apt install php8.3 php8.3-cli php8.3-fpm php8.3-mbstring php8.3-xml php8.3-curl \\
  php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl php8.3-sqlite3 php8.3-mysql unzip
php -v
php -m | grep -E 'mbstring|xml|curl|zip|gd|bcmath|intl|pdo'</pre>
<p>Sur RHEL/Alma : <code>dnf install php php-mbstring php-xml php-mysqlnd php-gd php-intl php-zip</code>.</p>
<h2>Droits d’écriture</h2>
<p>L’utilisateur du serveur web (souvent <code>www-data</code> ou <code>nginx</code>) doit écrire :</p>
<ul>
  <li><code>storage/</code> et sous-dossiers</li>
  <li><code>bootstrap/cache/</code></li>
</ul>
<pre>sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \\;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \\;</pre>
<div class="note">Si le wizard refuse l’install, relisez les chemins exacts affichés : OraNotes ne contourne pas un hébergeur qui interdit <code>chmod</code> ou <code>putenv</code>.</div>
<h2>Composer (sans Node en prod)</h2>
<pre>composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache &amp;&amp; php artisan route:cache</pre>
""",
    ),
    "windows.html": (
        "Windows",
        """
<h1>Windows (IIS / XAMPP / PowerShell)</h1>
<h2>PHP 8.3</h2>
<ol>
  <li>Installer PHP NTS x64 depuis windows.php.net, ou utiliser XAMPP <strong>uniquement si</strong> la version PHP ≥ 8.3.</li>
  <li>Activer dans <code>C:\\php\\php.ini</code> (chemin réel : celui de <code>php --ini</code>) :</li>
</ol>
<pre>extension=mbstring
extension=curl
extension=openssl
extension=fileinfo
extension=gd
extension=intl
extension=pdo_mysql
extension=pdo_sqlite
extension=zip</pre>
<h2>Vérification</h2>
<pre>php -v
php -m</pre>
<p>Si une extension manque, l’assistant web le dira et <strong>n’affirmera pas</strong> que l’environnement est supporté.</p>
<h2>Permissions</h2>
<p>Dans l’Explorateur : clic droit <code>storage</code> et <code>bootstrap\\cache</code> → Propriétés → Sécurité → IIS_IUSRS : Modifier.</p>
<p>PowerShell (admin) :</p>
<pre>icacls storage /grant IIS_IUSRS:(OI)(CI)M /T
icacls bootstrap\\cache /grant IIS_IUSRS:(OI)(CI)M /T</pre>
""",
    ),
    "apache.html": (
        "Apache",
        """
<h1>Apache 2.4</h1>
<p>Document root <strong>obligatoire</strong> : le dossier <code>public/</code>, jamais la racine du dépôt.</p>
<p>Fichier d’exemple : <code>scripts/apache-vhost.conf</code>.</p>
<pre>&lt;VirtualHost *:80&gt;
    ServerName notes.example.test
    DocumentRoot /var/www/oranotes/public
    &lt;Directory /var/www/oranotes/public&gt;
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
    ErrorLog ${APACHE_LOG_DIR}/oranotes-error.log
    CustomLog ${APACHE_LOG_DIR}/oranotes-access.log combined
&lt;/VirtualHost&gt;</pre>
<h2>Modules</h2>
<pre>sudo a2enmod rewrite headers
sudo apache2ctl configtest
sudo systemctl reload apache2</pre>
<p><code>public/.htaccess</code> Laravel doit rester en place. Sans <code>AllowOverride All</code>, les URLs Inertia cassent (404 hors <code>/</code>).</p>
<div class="note">HTTPS : terminez TLS en amont (Let’s Encrypt). En production <code>APP_URL</code> doit être <code>https://…</code>.</div>
""",
    ),
    "nginx.html": (
        "Nginx",
        """
<h1>Nginx + PHP-FPM</h1>
<p>Exemple : <code>scripts/nginx-server.conf</code>.</p>
<pre>server {
    listen 80;
    server_name notes.example.test;
    root /var/www/oranotes/public;
    index index.php;
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \\.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location /api/notes {
        # SSE collab : désactiver le buffer
        proxy_buffering off;
    }
}</pre>
<p>Le flux collab envoie <code>X-Accel-Buffering: no</code>. Sur Nginx, confirmez que le buffering n’avale pas les événements SSE.</p>
<p>Socket FPM : le chemin exact est celui de <code>/etc/php/8.3/fpm/pool.d/www.conf</code> (<code>listen =</code>).</p>
""",
    ),
    "iis.html": (
        "IIS",
        """
<h1>IIS (Windows Server)</h1>
<p>OraNotes fonctionne sur IIS <strong>si</strong> PHP 8.3 via FastCGI est installé (Web Platform Installer / phpmanager). Sans FastCGI, l’install n’est <strong>pas</strong> supportée.</p>
<ol>
  <li>Site IIS → Chemin physique = dossier <code>public</code>.</li>
  <li>Handler Mapping → FastCGI <code>*.php</code> → <code>C:\\php\\php-cgi.exe</code> (chemin réel de votre PHP).</li>
  <li>URL Rewrite : importer les règles de <code>public/web.config</code> Laravel, ou copier <code>scripts/iis-web.config</code> vers <code>public/web.config</code>.</li>
  <li>Permissions IIS_IUSRS sur <code>storage</code> et <code>bootstrap\\cache</code>.</li>
</ol>
<div class="note">L’assistant web détecte IIS via les en-têtes serveur. Il n’écrit pas dans le Gestionnaire IIS : vous devez créer le site vous-même.</div>
""",
    ),
    "cpanel.html": (
        "cPanel",
        """
<h1>cPanel / shared hosting</h1>
<p>Compatible seulement si l’hébergeur offre <strong>PHP 8.3+</strong>, Composer (ou vendor déjà livré), et un document root personnalisable vers <code>public/</code>.</p>
<h2>Étapes typiques</h2>
<ol>
  <li>Fichiers → déposer le paquet (sans <code>.env</code> secret).</li>
  <li>Domaines → racine du domaine = <code>…/oranotes/public</code>.</li>
  <li>PHP Selector → 8.3, extensions mbstring, xml, curl, zip, gd, intl, pdo.</li>
  <li>MySQL Databases → créer base + user, puis wizard <code>/install</code>.</li>
</ol>
<p>Si cPanel refuse de pointer hors <code>public_html</code> : déplacez le contenu de <code>public/</code> dans <code>public_html</code> et remontez <code>index.php</code> :</p>
<pre>require __DIR__.'/../bootstrap/app.php';</pre>
<p>devient, si l’app est un niveau au-dessus de public_html :</p>
<pre>require __DIR__.'/../oranotes/bootstrap/app.php';</pre>
<div class="note">Nous ne contournons pas <code>disable_functions</code> (ex. <code>proc_open</code>, <code>putenv</code>). Si Composer ne peut pas tourner sur l’hébergeur, uploadez un build déjà empaqueté (<code>scripts/package-dist.sh</code>).</div>
""",
    ),
    "webuzo.html": (
        "Webuzo",
        """
<h1>Webuzo</h1>
<ol>
  <li>Softaculous / Webuzo → PHP 8.3, activer les extensions listées dans l’assistant.</li>
  <li>Créer un vhost dont le <em>Document Root</em> est <code>/home/…/oranotes/public</code>.</li>
  <li>Base MySQL via le panneau, puis ouvrir <code>https://votre-domaine/install</code>.</li>
</ol>
<p>Webuzo n’a pas d’installateur Softaculous officiel OraNotes : c’est une app PHP générique. Si le panneau impose PHP 8.1, <strong>n’installez pas</strong> — Laravel 13 exige 8.3+.</p>
""",
    ),
    "plesk.html": (
        "Plesk",
        """
<h1>Plesk</h1>
<ol>
  <li>Domaines → Hôte → « Racine du document » = <code>httpdocs</code> remplacé par le chemin <code>oranotes/public</code>, ou uploader dans <code>httpdocs</code> en corrigeant <code>index.php</code>.</li>
  <li>PHP : 8.3 FPM, extensions mbstring, xml, curl, zip, gd, intl, pdo_mysql.</li>
  <li>Bases de données → MySQL/MariaDB.</li>
  <li>SSL Let’s Encrypt dans Plesk, puis <code>APP_URL=https://…</code>.</li>
</ol>
<p>Ouvrir un ticket hébergeur si « additional open_basedir restrictions » bloquent <code>storage</code> : OraNotes ne désactive pas open_basedir pour vous.</p>
""",
    ),
    "mysql.html": (
        "MySQL",
        """
<h1>MySQL 8</h1>
<pre>CREATE DATABASE oranotes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'oranotes'@'127.0.0.1' IDENTIFIED BY 'mot-de-passe-fort';
GRANT ALL ON oranotes.* TO 'oranotes'@'127.0.0.1';
FLUSH PRIVILEGES;</pre>
<p><code>.env</code> :</p>
<pre>DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oranotes
DB_USERNAME=oranotes
DB_PASSWORD=…</pre>
<p>L’assistant teste réellement <code>PDO</code> avant de migrer. La recherche utilise <code>FULLTEXT</code> sur MySQL (migration ignorée sur SQLite).</p>
""",
    ),
    "mariadb.html": (
        "MariaDB",
        """
<h1>MariaDB</h1>
<p>Même procédure que MySQL. Vérifiez <code>mariadbd --version</code> ≥ 10.6 recommandé.</p>
<p>FULLTEXT InnoDB est utilisé si le driver PDO est <code>mysql</code> (MariaDB parle le protocole MySQL). Sur SQLite de dev/tests, OraNotes retombe sur <code>LIKE</code>.</p>
<div class="note">Si <code>mode strict</code> est actif, les migrations Laravel 13 sont déjà compatibles. En cas d’erreur de charset, recréez la base en <code>utf8mb4</code>.</div>
""",
    ),
    "installer.html": (
        "Assistant web",
        """
<h1>Assistant d’installation <code>/install</code></h1>
<ol>
  <li>Bienvenue / licence MIT</li>
  <li>Contrôles : PHP, zip, mbstring, XML, cURL, GD, BCMath, Intl, PDO, disque, droits</li>
  <li>Base : driver, hôte, port, nom, user, mot de passe — test de connexion réel</li>
  <li>Application : nom, URL, env, fuseau, locale, mail, filesystem</li>
  <li>Compte admin (mot de passe fort)</li>
  <li>Install : <code>APP_KEY</code>, migrations, storage:link, caches</li>
  <li>Terminé : URL, accès admin, recommandations HTTPS / <code>APP_DEBUG=false</code></li>
</ol>
<h2>Sécurité</h2>
<ul>
  <li>Après succès : fichier <code>storage/app/installed.lock</code>.</li>
  <li>Si <code>APP_KEY</code> existe déjà <strong>et</strong> la table <code>users</code> est là, le wizard est refusé (pas de réinstall furtive).</li>
  <li>En <code>APP_ENV=testing</code> le wizard est considéré déjà installé.</li>
  <li>Aucune secret n’est renvoyé dans les pages « terminé ».</li>
</ul>
<p>Réouvrir le wizard : procédure explicite — supprimer <code>installed.lock</code> <em>et</em> comprendre que vous réinitialisez une instance. Ce n’est pas un bouton dans l’UI.</p>
""",
    ),
    "updates.html": (
        "Mises à jour",
        """
<h1>Mises à jour officielles</h1>
<p>Source unique : API GitHub Releases du dépôt configuré (<code>ORANOTES_UPDATE_REPO</code>, défaut <code>tallyhome/OraNotes</code>). <strong>Jamais</strong> une URL arbitraire téléchargée puis exécutée.</p>
<ol>
  <li>Admin → Mises à jour : version courante / disponible / changelog.</li>
  <li>Contrôles : PHP, extensions, disque, permissions, version minimale.</li>
  <li>Téléchargement de l’artefact de release + vérification d’intégrité (hash).</li>
  <li>Backup fichiers clés + SQLite si utilisé + enregistrement de version.</li>
  <li>Mode maintenance, overlay du code (hors <code>.env</code>, <code>storage</code>, <code>node_modules</code>), migrations, caches.</li>
  <li>Échec : tentative de restauration, sortie de maintenance, logs précis. <strong>Le rollback n’est pas atomique.</strong></li>
</ol>
<p>Protections : refus de downgrade, traversal <code>..</code>, écriture hors projet, SSRF (hôte figé <code>api.github.com</code>).</p>
""",
    ),
    "collaboration.html": (
        "Collaboration",
        """
<h1>Collaboration temps réel</h1>
<p>Les nœuds texte OraEditor sont fusionnés avec <strong>Yjs</strong> (CRDT). Le transport est <strong>SSE + POST</strong> d’updates, autorisé par Policy <code>view</code>/<code>update</code> avant toute souscription. Ce n’est pas un polling du document JSON, ni du last-write-wins silencieux.</p>
<p>Laravel Reverb n’est pas livré : conflit Guzzle PSR-7 v3. Le même modèle CRDT pourra passer sur WebSocket plus tard.</p>
<ul>
  <li>Lecture : état + présence, pas d’updates.</li>
  <li>Édition : POST updates + snapshot périodique (~4 s).</li>
  <li>Révocation mid-session : événement SSE <code>revoked</code>.</li>
  <li>Hors-ligne : brouillon localStorage + indicateur, resync à la reconnexion.</li>
</ul>
<p>Détail : <a href="../docs/collaboration.md">docs/collaboration.md</a>.</p>
""",
    ),
    "security.html": (
        "Sécurité",
        """
<h1>Sécurité</h1>
<ul>
  <li>Policies + AccessService : UUID connus ≠ accès (archivé / corbeille / partage).</li>
  <li>Comptes désactivés : login générique (anti-énumération), middleware <code>active</code>.</li>
  <li>Uploads : MIME réel, taille, extension, hors exécutable.</li>
  <li>CSP : Report-Only par défaut, sans <code>unsafe-eval</code> — voir <a href="../docs/csp.md">docs/csp.md</a>.</li>
  <li>Journal admin : acteur, action, cible, IP/UA — jamais mots de passe / tokens.</li>
  <li>Suppression comptes : <a href="../docs/user-deletion.md">docs/user-deletion.md</a>.</li>
</ul>
<p>Après install : <code>APP_DEBUG=false</code>, HTTPS, permissions <code>storage</code>, ne pas commiter <code>.env</code>.</p>
""",
    ),
    "troubleshooting.html": (
        "Dépannage",
        """
<h1>Dépannage</h1>
<table>
  <tr><th>Symptôme</th><th>Cause fréquente</th><th>Action</th></tr>
  <tr><td>404 sur toutes les routes sauf /</td><td>Document root ≠ public/ ou rewrite off</td><td>Apache AllowOverride / Nginx try_files</td></tr>
  <tr><td>500 après install</td><td>storage non inscriptible</td><td>chown www-data storage bootstrap/cache</td></tr>
  <tr><td>Vite manifest missing</td><td>Assets non buildés</td><td><code>npm ci &amp;&amp; npm run build</code> ou paquet dist</td></tr>
  <tr><td>Wizard inaccessible</td><td>Déjà installé</td><td>Normal si installed.lock + users</td></tr>
  <tr><td>Collab figé derrière Nginx</td><td>Buffering SSE</td><td>X-Accel-Buffering, proxy_buffering off</td></tr>
  <tr><td>Mise à jour refusée</td><td>URL non officielle / downgrade / disque</td><td>Lire le message admin, logs laravel.log</td></tr>
  <tr><td>Note invisible via UUID</td><td>Archivée / trash / pas de droit</td><td>Comportement voulu depuis 1.0.4-hardening</td></tr>
</table>
<p>Logs : <code>storage/logs/laravel.log</code>. Santé : <code>/admin/health</code>.</p>
""",
    ),
}


def main() -> None:
    DOC.mkdir(parents=True, exist_ok=True)
    (DOC / "assets").mkdir(exist_ok=True)
    for name, (title, body) in PAGES.items():
        (DOC / name).write_text(page(title, body), encoding="utf-8")
    print(f"Wrote {len(PAGES)} HTML pages in {DOC}")


if __name__ == "__main__":
    main()
