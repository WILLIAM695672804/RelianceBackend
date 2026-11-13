#!/usr/bin/env bash
#
# Écrit « RelianceBackend/.env » pour la production.
#
#     bash deploiement/regler.sh
#
# Pourquoi un script et non des commandes à coller : le terminal distant indente
# le collage, ce qui casse les délimiteurs de « heredoc », il coupe les lignes
# longues en y insérant de vrais retours à la ligne, et « read » consomme la
# ligne suivante du collage au lieu d'attendre une frappe. Ici tout se passe
# dans un seul processus.
#
# Rien n'est écrasé sans demande : si .env existe, le script s'arrête, sauf
# avec --forcer, qui en garde une copie sous .env.precedent.

set -euo pipefail

cd "$(dirname "$0")/.."        # racine de RelianceBackend

rouge() { printf '\033[31m%s\033[0m\n' "$*" >&2; }
vert()  { printf '\033[32m%s\033[0m\n' "$*"; }
titre() { printf '\n\033[1m%s\033[0m\n' "$*"; }

FORCER=0
[ "${1:-}" = "--forcer" ] && FORCER=1

if [ -f .env ] && [ "$FORCER" = 0 ]; then
    rouge "Le fichier .env existe déjà."
    echo "    Pour le remplacer :  bash deploiement/regler.sh --forcer"
    exit 1
fi

command -v openssl >/dev/null || { rouge "openssl absent : apk/apt install openssl"; exit 1; }

titre "Réglages de Reliance en production"

demander() {           # demander <invite> <défaut>
    local reponse
    read -rp "  $1 [$2] : " reponse </dev/tty
    printf '%s' "${reponse:-$2}"
}

sans_espace() {
    local valeur
    while :; do
        valeur=$(demander "$1" "$2")
        case "$valeur" in
            '')         rouge "  Valeur vide. Recommencez." ;;
            *[\ \'\"]*) rouge "  Ni espace ni guillemet ici. Recommencez." ;;
            *)          printf '%s' "$valeur"; return ;;
        esac
    done
}

secret() {             # secret <invite>  — saisie masquée, obligatoire
    local valeur
    while :; do
        printf "  %s : " "$1"
        read -rs valeur </dev/tty; echo
        [ -n "$valeur" ] && { printf '%s' "$valeur"; return; }
        rouge "  Obligatoire. Recommencez."
    done
}

DOMAINE=$(sans_espace "Nom de domaine" "reliance.the7ing.site")

titre "Courriel — obligatoire"
echo "  Sans SMTP réel, aucune vérification d'adresse ni réinitialisation de"
echo "  mot de passe n'atteint l'utilisateur, et production:preflight refuse"
echo "  le déploiement."
MAIL_HOST=$(sans_espace "Serveur SMTP" "")
MAIL_PORT=$(sans_espace "Port SMTP" "587")
MAIL_USERNAME=$(sans_espace "Compte SMTP" "")
MAIL_PASSWORD=$(secret "Mot de passe SMTP")
MAIL_FROM_ADDRESS=$(sans_espace "Adresse d'expédition" "no-reply@$DOMAINE")

titre "NotchPay — obligatoire"
echo "  Les clés se prennent sur business.notchpay.co. Sans la clé de"
echo "  signature, les webhooks sont refusés et aucun paiement n'est confirmé."
NOTCHPAY_PUBLIC_KEY=$(sans_espace "Clé publique" "")
NOTCHPAY_PRIVATE_KEY=$(secret "Clé privée")
NOTCHPAY_HASH_KEY=$(secret "Clé de signature des webhooks")

titre "Premier administrateur"
ADMIN_EMAIL=$(sans_espace "Adresse de l'administrateur" "")
ADMIN_PASSWORD=$(secret "Mot de passe de l'administrateur")

titre "Secrets engendrés"
# « -hex » et non « -base64 » : ces valeurs voyagent dans des URL de connexion
# et dans des fichiers lus par le shell, où « / » et « + » posent problème.
DB_PASSWORD=$(openssl rand -hex 24)
PUSHER_APP_ID=$(openssl rand -hex 8)
PUSHER_APP_KEY=$(openssl rand -hex 16)
PUSHER_APP_SECRET=$(openssl rand -hex 24)
# APP_KEY : Laravel attend 32 octets en base64. La commande « key:generate »
# ferait la même chose, mais elle exige PHP, absent de l'hôte.
APP_KEY="base64:$(openssl rand -base64 32)"
vert "  mot de passe de la base, clés Reverb et APP_KEY"

[ -f .env ] && cp -f .env .env.precedent

{
    echo "# Engendré par deploiement/regler.sh le $(date -Is)"
    echo "APP_NAME=Reliance"
    echo "APP_ENV=production"
    echo "APP_DEBUG=false"
    echo "APP_KEY=$APP_KEY"
    echo "APP_URL=https://$DOMAINE"
    echo "FRONTEND_URL=https://$DOMAINE"
    echo "DOMAINE=$DOMAINE"
    echo "TRUSTED_PROXIES=*"
    echo "LOG_CHANNEL=stack"
    echo "LOG_LEVEL=warning"
    echo
    echo "DB_CONNECTION=pgsql"
    echo "DB_DATABASE=reliance_db"
    echo "DB_USERNAME=reliance_user"
    echo "DB_PASSWORD=$DB_PASSWORD"
    echo
    echo "SESSION_DRIVER=redis"
    echo "SESSION_LIFETIME=120"
    echo "SESSION_DOMAIN=$DOMAINE"
    echo "SESSION_SECURE_COOKIE=true"
    echo "SANCTUM_STATEFUL_DOMAINS=$DOMAINE"
    echo
    echo "CACHE_DRIVER=redis"
    echo "QUEUE_CONNECTION=sync"
    echo "REDIS_CLIENT=phpredis"
    echo "REDIS_PASSWORD=null"
    echo
    echo "BROADCAST_DRIVER=pusher"
    echo "PUSHER_APP_ID=$PUSHER_APP_ID"
    echo "PUSHER_APP_KEY=$PUSHER_APP_KEY"
    echo "PUSHER_APP_SECRET=$PUSHER_APP_SECRET"
    echo "PUSHER_APP_CLUSTER=mt1"
    echo
    echo "MAIL_MAILER=smtp"
    echo "MAIL_HOST=$MAIL_HOST"
    echo "MAIL_PORT=$MAIL_PORT"
    echo "MAIL_USERNAME=$MAIL_USERNAME"
    echo "MAIL_PASSWORD=$MAIL_PASSWORD"
    echo "MAIL_ENCRYPTION=tls"
    echo "MAIL_FROM_ADDRESS=$MAIL_FROM_ADDRESS"
    echo "MAIL_FROM_NAME=Reliance"
    echo
    echo "NOTCHPAY_BASE_URL=https://api.notchpay.co"
    echo "NOTCHPAY_PUBLIC_KEY=$NOTCHPAY_PUBLIC_KEY"
    echo "NOTCHPAY_PRIVATE_KEY=$NOTCHPAY_PRIVATE_KEY"
    echo "NOTCHPAY_HASH_KEY=$NOTCHPAY_HASH_KEY"
    echo
    echo "SMS_DRIVER=log"
    echo "SMS_SENDER=Reliance"
    echo "EXPO_PUSH_URL=https://exp.host/--/api/v2/push/send"
    echo
    echo "REQUIRE_SUBSCRIPTION=false"
    echo "REQUIRE_2FA_CLIENTS=true"
    echo "SSE_MAX_SECONDS=300"
    echo "ADMIN_EMAIL=$ADMIN_EMAIL"
    echo "ADMIN_PASSWORD=$ADMIN_PASSWORD"
} > .env
chmod 600 .env

titre "Relecture"
if docker compose --env-file .env -f deploiement/docker-compose.production.yml \
        config >/dev/null 2>&1; then
    vert "  docker compose relit .env sans erreur"
else
    rouge "  docker compose refuse la composition :"
    docker compose --env-file .env -f deploiement/docker-compose.production.yml \
        config 2>&1 | tail -5
    exit 1
fi

sed -E 's/^(APP_KEY|DB_PASSWORD|PUSHER_APP_SECRET|MAIL_PASSWORD|NOTCHPAY_PRIVATE_KEY|NOTCHPAY_HASH_KEY|ADMIN_PASSWORD)=.*/\1=…/' .env | sed 's/^/  /'

titre "Fichier .env écrit (droits 600)"
echo "  Étape suivante :  bash deploiement/deployer.sh"
