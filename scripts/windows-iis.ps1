# Aide IIS — n'automatise pas un serveur verrouillé par l'hébergeur.
# Exécuter en administrateur après installation de PHP 8.3 FastCGI.
$ErrorActionPreference = "Stop"
Write-Host "PHP:"
php -v
Write-Host "Extensions requises: mbstring xml curl zip gd bcmath intl pdo_mysql pdo_sqlite"
php -m
Write-Host "Document root IIS = <repo>\public"
Write-Host "Accorder Modifier à IIS_IUSRS sur storage et bootstrap\cache :"
Write-Host '  icacls storage /grant IIS_IUSRS:(OI)(CI)M /T'
Write-Host '  icacls bootstrap\cache /grant IIS_IUSRS:(OI)(CI)M /T'
Write-Host "Copier scripts\iis-web.config vers public\web.config si besoin."
