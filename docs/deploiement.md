# Déploiement en production — RelianceBackend

## 1. Prérequis

| Composant | Exigence |
|---|---|
| PHP | 8.3 (FPM), extensions `pgsql`, `pdo_pgsql`, `mbstring`, `intl`, `gd` |
| PostgreSQL | ≥ 13, extension **PostGIS** (créée par la migration `create_locations_table`) |
| Redis | recommandé pour `CACHE_DRIVER` (jetons SSE, limiteurs de débit partagés entre processus) |
| Proxy inverse | Nginx (TLS, mise en mémoire tampon désactivée pour le SSE, proxy WebSocket) |
| Superviseur | `supervisord` ou `systemd` pour le serveur WebSocket |

## 2. Configuration

Partir de `.env.example` ; les commentaires indiquent la valeur de production de chaque variable.
Les secrets (`APP_KEY`, `DB_PASSWORD`, `NOTCHPAY_*`, `PUSHER_APP_SECRET`, `MAIL_PASSWORD`, `ADMIN_PASSWORD`)
proviennent du gestionnaire de secrets de l'hébergeur et ne sont jamais versionnés (`.env.*` est ignoré par git).

Points bloquants vérifiés par `reliance:verifier-production` :
`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` et `FRONTEND_URL` en https, `LOG_LEVEL` ≠ `debug`,
`SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS` sans domaine de développement,
`NOTCHPAY_PRIVATE_KEY` et `NOTCHPAY_HASH_KEY` renseignées, SMTP réel, PostGIS présente,
aucune pièce KYC sur le disque public.

## 3. Procédure de déploiement

```bash
php artisan down --retry=60                        # maintenance (réponse 503 avec Retry-After)
git fetch && git checkout <tag>
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan db:seed --force                        # 1er déploiement : référentiels + administrateur (ADMIN_PASSWORD)
php artisan kyc:privatiser-stockage                # idempotente ; reprise des pièces déposées avant le correctif
php artisan config:cache && php artisan route:cache && php artisan event:cache
php artisan reliance:verifier-production           # code de sortie ≠ 0 : interrompre le déploiement
sudo systemctl reload php8.3-fpm
sudo supervisorctl restart reliance-websockets
php artisan up
```

`db:seed` ne crée **aucun compte de démonstration** hors `local`/`testing`, et ne crée l'administrateur
que si `ADMIN_PASSWORD` (12 caractères minimum) est fourni.

## 4. Processus permanents

**Planificateur** (expiration des propositions chaque minute, absences SLA toutes les dix minutes,
calibrage des prix à 03:00) — crontab de l'utilisateur web :

```cron
* * * * * cd /srv/reliance/backend && php artisan schedule:run >> /dev/null 2>&1
```

**Serveur WebSocket** (`/etc/supervisor/conf.d/reliance-websockets.conf`) :

```ini
[program:reliance-websockets]
command=php /srv/reliance/backend/artisan websockets:serve --host=127.0.0.1 --port=6001
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/log/reliance/websockets.log
```

Les courriels étant envoyés de manière synchrone (aucune notification `ShouldQueue`),
aucun worker de file d'attente n'est nécessaire tant que `QUEUE_CONNECTION=sync`.

## 5. Nginx

```nginx
location /api/v1/proposals/stream {          # SSE : pas de mise en mémoire tampon, délai long
    proxy_buffering off;                      # (ou fastcgi_buffering off si FastCGI direct)
    fastcgi_read_timeout 330s;                # > SSE_MAX_SECONDS
    include fastcgi_params; fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
}
location /app/ {                              # WebSocket (Pusher) en wss
    proxy_pass http://127.0.0.1:6001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
}
```

Si Nginx et PHP-FPM sont sur des hôtes distincts, renseigner `TRUSTED_PROXIES` : sans cela,
les limiteurs par IP s'appliquent à l'adresse du proxy, donc à tous les utilisateurs à la fois.

Chaque connexion SSE mobilise un processus PHP-FPM pendant `SSE_MAX_SECONDS` : dimensionner
`pm.max_children` en conséquence.

## 6. Retour arrière

1. `php artisan down`, puis `git checkout <tag précédent>` et `composer install --no-dev`.
2. Les migrations de la version fautive : `php artisan migrate:rollback --step=<n>` **uniquement**
   après sauvegarde (`pg_dump -Fc`), car un rollback détruit les colonnes ajoutées.
3. `config:cache`, `route:cache`, rechargement de PHP-FPM, `php artisan up`.

Seuils déclencheurs : taux de réponses 5xx > 2 % sur 5 minutes, échec des webhooks NotchPay (401/503
dans les journaux), échec de connexion ou d'inscription.

## 7. Risques résiduels connus

- **Laravel 10 hors support** : trois avis non corrigés sur cette branche (consignés dans
  `composer.json`, `config.audit.ignore`, avec leur mesure d'atténuation). Seule une migration vers
  Laravel 12 les résout ; elle impose de remplacer `beyondcode/laravel-websockets` (abandonné,
  incompatible avec Laravel 11 et au-delà) par Laravel Reverb.
- **Pièces jointes de messagerie** : stockées sur le disque public (`MessageAttachment`), donc
  accessibles à quiconque obtient l'URL. Même traitement que le KYC à prévoir.
- **Flux SSE** : un client doit demander un nouveau jeton (`POST /v1/proposals/stream-token`)
  avant chaque reconnexion ; aucun client actuel n'utilise le flux.
