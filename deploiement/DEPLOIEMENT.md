# Mise en ligne de Reliance

Second locataire du serveur : SASI y tourne déjà et détient, par son frontal
Caddy, les ports 80 et 443. Reliance ne publie aucun port.

```
Internet ─► Caddy (frontal partagé, 80/443)
              ├─ smartpredict.the7ing.site ──► SASI
              └─ reliance.the7ing.site ─────► reliance-web (nginx)
                                                ├─ /api, /sanctum, /broadcasting ─► api (php-fpm)
                                                ├─ /app, /apps ───────────────────► reverb (WebSocket)
                                                ├─ /storage ──────────────────────► volume, lecture seule
                                                └─ le reste ──────────────────────► interface React
```

Six conteneurs : `base` (PostgreSQL 16 + PostGIS), `cache` (Redis), `api`
(php-fpm 8.3), `reverb`, `planificateur`, `web` (nginx + interface compilée).

## Un seul nom de domaine

L'authentification repose sur Sanctum en mode SPA, donc sur un cookie de
session et un jeton CSRF. Entre deux origines, ce cookie est « cross-site » : il
faut `SameSite=None`, l'en-tête `X-XSRF-TOKEN`, une configuration CORS exacte,
et la moindre erreur rend un `419` sans explication. Sur une origine unique,
aucune de ces pièces n'est nécessaire — c'est nginx qui répartit par chemin.

## 1. Avant de commencer

Trois choses ne s'improvisent pas au moment du déploiement :

| À obtenir | Pourquoi | Sans quoi |
|---|---|---|
| Compte SMTP | vérification d'adresse, réinitialisation de mot de passe | `reliance:verifier-production` refuse le déploiement |
| Clés NotchPay (`business.notchpay.co`) : publique, privée, signature | encaissement et webhooks | idem ; et sans la clé de signature, tout webhook est refusé (503) |
| Enregistrement DNS `A` pour `reliance` vers l'IP du serveur | certificat Let's Encrypt | le script s'arrête avant de construire |

Le DNS se règle chez LWS, rubrique **Zone DNS** — jamais « Sous-domaines », qui
écraserait les enregistrements existants. TTL de quinze minutes.

## 2. Récupérer le code

Les deux dépôts doivent être côte à côte : la composition compile l'interface
depuis `../RelianceFrontend`.

```bash
mkdir -p ~/reliance && cd ~/reliance
git clone https://github.com/WILLIAM695672804/RelianceBackend.git
git clone -b devFrontend https://github.com/WILLIAM695672804/RelianceFrontend.git
cd RelianceBackend
```

`-b devFrontend` n'est pas un détail : la branche `main` de l'interface a
soixante-trois commits de retard. Sans elle, vous déploieriez une version
ancienne, et le `Dockerfile` de l'interface n'y serait même pas.

## 3. Régler

```bash
bash deploiement/regler.sh
```

Il demande le domaine, le SMTP, les clés NotchPay et le premier administrateur ;
il engendre lui-même `APP_KEY`, le mot de passe de la base et les trois clés de
Reverb, écrit `.env` en `600`, et vérifie que docker compose le relit.

## 4. Déployer

```bash
bash deploiement/deployer.sh --semer
```

`--semer` ne sert que la première fois : il remplit les tables de référence et
crée l'administrateur. Ensuite, `bash deploiement/deployer.sh` suffit.

Déroulé : contrôles, réseau partagé, construction des images, démarrage, attente
de la base, migrations, vérification de l'extension PostGIS, semences, caches de
configuration, `reliance:verifier-production`, dépôt du fichier de site dans le
frontal et rechargement, puis relevé d'état.

**Comptez quinze à vingt-cinq minutes la première fois** : `composer install` et
la compilation de l'interface sont longues sur deux cœurs ARM.

## 5. Vérifier

```bash
bash deploiement/deployer.sh --etat
```

Puis ouvrir `https://reliance.the7ing.site`.

## Mémoire : le point à surveiller

La machine a 3,8 Gio et 2 Gio d'échange, partagés avec SASI. Chaque service
porte une borne (`mem_limit`) pour qu'une fuite dans l'un n'emporte pas les
deux applications : au total 1,7 Gio au plus pour Reliance.

Le pic n'est pas à l'exécution mais à la **compilation de l'interface** : React
19, MapLibre et TypeScript demandent près de 1,5 Gio. `NODE_OPTIONS` les borne
dans le Dockerfile. Si la construction meurt sur un `Killed` sans autre message,
c'est la mémoire : portez l'échange à 4 Go avant de recommencer.

```bash
swapoff /echange && fallocate -l 4G /echange && chmod 600 /echange
mkswap /echange && swapon /echange && free -h
```

## Mettre à jour

```bash
cd ~/reliance/RelianceBackend && git pull
cd ../RelianceFrontend && git pull            # branche devFrontend
cd ../RelianceBackend && bash deploiement/deployer.sh
```

Le déploiement reconstruit ce qui a changé, rejoue les migrations, recompile les
caches et relance les services. Rien à recréer à la main.

## Sauvegarder

```bash
mkdir -p ~/sauvegardes
cat > ~/sauvegarder-reliance.sh <<'FIN'
#!/bin/sh
jour=$(date +%F)
cd "$HOME/reliance/RelianceBackend" || exit 1
docker compose --env-file .env -f deploiement/docker-compose.production.yml \
    exec -T base pg_dump -U reliance_user -d reliance_db --format=custom \
    > "$HOME/sauvegardes/reliance-$jour.dump"
docker run --rm -v reliance_storage:/s -v "$HOME/sauvegardes":/out alpine \
    tar czf "/out/reliance-storage-$jour.tgz" -C /s .
find "$HOME/sauvegardes" -name 'reliance-*' -mtime +14 -delete
FIN
chmod +x ~/sauvegarder-reliance.sh
(crontab -l 2>/dev/null; echo "0 3 * * * $HOME/sauvegarder-reliance.sh") | crontab -
```

Le volume `storage` compte autant que la base : il contient les pièces jointes
et les documents d'identité. Une sauvegarde jamais restaurée n'est pas une
sauvegarde — essayez-en une sur une base vide.

## Pannes courantes

| Symptôme | Cause probable | Vérification |
|---|---|---|
| `Killed` pendant la construction | mémoire épuisée | `free -h`, puis porter l'échange à 4 Go |
| `419` à la connexion | `SESSION_DOMAIN` ou `SANCTUM_STATEFUL_DOMAINS` ne correspond pas au domaine servi | `grep -E 'SESSION_DOMAIN|SANCTUM' .env` |
| `502` sur `/api` | php-fpm arrêté | `... logs --tail 30 api` |
| interface vide, console en erreur sur `127.0.0.1` | interface compilée sans `VITE_API_URL` | reconstruire : `... build --no-cache web` |
| WebSocket qui ne se connecte pas | `PUSHER_APP_KEY` différent entre `.env` et l'interface compilée | reconstruire `web` après tout changement de clé |
| webhooks NotchPay en `503` | `NOTCHPAY_HASH_KEY` absente | `reliance:verifier-production` |
| `extension postgis absente` | image de base sans PostGIS | la composition impose `imresamu/postgis`, construction ARM64 du projet PostGIS |

## Ce qui n'a pas été éprouvé

Cette pile a été écrite et vérifiée **statiquement** : YAML et scripts validés,
images et paquets confirmés disponibles en ARM64, routes et commandes relues
dans le code. Elle n'a jamais été exécutée. La première mise en service de SASI,
dans les mêmes conditions, a révélé trois pannes qu'aucun test ne pouvait
voir — toutes des hypothèses sur l'environnement, vraies en développement et
fausses dans un conteneur. Attendez-vous à une ou deux corrections.
