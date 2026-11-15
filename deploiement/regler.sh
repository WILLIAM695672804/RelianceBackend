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

# Valeur d'une clé dans le .env existant, guillemets retirés. Elle sert de
# proposition : on ne redemande pas ce qui est déjà su.
ANCIEN=""
[ -f .env ] && ANCIEN=.env
[ -z "$ANCIEN" ] && [ -f .env.precedent ] && ANCIEN=.env.precedent

lire_ancien() {        # lire_ancien <clé>
    [ -n "$ANCIEN" ] || return 0
    # Guillemets simples (écriture actuelle) ou doubles (fichiers antérieurs au 15/11) retirés.
    sed -n "s/^$1=//p" "$ANCIEN" | head -1 | sed -e "s/^[\"']//" -e "s/[\"']\$//"
}

titre "Réglages de Reliance en production"
[ -n "$ANCIEN" ] && vert "  valeurs reprises de $ANCIEN — « Entrée » les conserve"

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

secret_ou_conserver() {   # secret_ou_conserver <invite> <clé du .env>
    local actuel
    actuel=$(lire_ancien "$2")
    if [ -n "$actuel" ]; then
        printf "  %s [inchangé] : " "$1" >&2
        local valeur
        read -rs valeur </dev/tty; echo >&2
        [ -z "$valeur" ] && { printf '%s' "$actuel"; return; }
        case "$valeur" in
            *[\'\\]*) rouge "  Ni apostrophe ni contre-oblique. Valeur inchangée."
                     printf '%s' "$actuel"; return ;;
        esac
        printf '%s' "$valeur"; return
    fi
    secret "$1"
}

secret() {             # secret <invite>  — saisie masquée, obligatoire
    local valeur
    while :; do
        # Les secrets sont écrits entre apostrophes : entre guillemets doubles,
        # docker compose remplace « $mot » par une variable — vide — et le
        # conteneur recevait un mot de passe tronqué (SMTP refusé, 503 au renvoi
        # du lien de vérification). Seules l'apostrophe et la contre-oblique
        # restent interdites ; « $ », « # » et le guillemet double sont permis.
        :
        # Sur la sortie d'erreur : cette fonction est appelée depuis une
        # substitution de commande, qui capture la sortie standard. L'invite
        # écrite là ne s'afficherait pas et finirait dans la valeur — le .env
        # portait « MAIL_PASSWORD=  Mot de passe SMTP : … » sur plusieurs
        # lignes, et docker compose refusait le fichier.
        printf "  %s : " "$1" >&2
        read -rs valeur </dev/tty; echo >&2
        case "$valeur" in
            '')      rouge "  Obligatoire. Recommencez."; continue ;;
            *[\'\\]*) rouge "  Ni apostrophe ni contre-oblique ici. Recommencez."; continue ;;
        esac
        printf '%s' "$valeur"; return
    done
}

DOMAINE=$(sans_espace "Nom de domaine" "$(lire_ancien DOMAINE || echo reliance.the7ing.site)")

titre "Courriel — obligatoire"
echo "  Sans SMTP réel, aucune vérification d'adresse ni réinitialisation de"
echo "  mot de passe n'atteint l'utilisateur, et production:preflight refuse"
echo "  le déploiement."
MAIL_HOST=$(sans_espace "Serveur SMTP" "$(lire_ancien MAIL_HOST)")
MAIL_PORT=$(sans_espace "Port SMTP (465 pour SSL, 587 pour STARTTLS)" "$(lire_ancien MAIL_PORT || echo 465)")

# Le port décide du chiffrement, et les confondre donne un échec de
# négociation TLS qui ne nomme jamais sa cause : 465 ouvre une liaison déjà
# chiffrée, 587 commence en clair et bascule par STARTTLS.
if [ "$MAIL_PORT" = "465" ]; then
    MAIL_ENCRYPTION=ssl
else
    MAIL_ENCRYPTION=tls
fi
vert "  chiffrement : $MAIL_ENCRYPTION"

MAIL_USERNAME=$(sans_espace "Compte SMTP" "$(lire_ancien MAIL_USERNAME)")
MAIL_PASSWORD=$(secret_ou_conserver "Mot de passe SMTP" MAIL_PASSWORD)
# Par défaut le compte SMTP lui-même : la plupart des serveurs refusent
# d'expédier sous une adresse qui n'est pas celle du compte authentifié, et
# « no-reply@reliance.the7ing.site » n'est pas une boîte qui existe.
MAIL_FROM_ADDRESS=$(sans_espace "Adresse d'expédition" "$MAIL_USERNAME")

titre "Paiements"
echo "  En préproduction, le parcours de paiement se déroule en entier sans"
echo "  compte marchand ni argent : un paiement part « en attente », comme un"
echo "  push USSD, puis se confirme au bout de quelques secondes."
reponse=$(demander "Simuler les paiements ? (o/n)" "o")
case "$reponse" in
    [oO]*) PREPRODUCTION=true ;;
    *)     PREPRODUCTION=false ;;
esac

NOTCHPAY_PUBLIC_KEY=""
NOTCHPAY_PRIVATE_KEY=""
NOTCHPAY_HASH_KEY=""
if [ "$PREPRODUCTION" = false ]; then
    titre "NotchPay — obligatoire"
    echo "  Les clés se prennent sur business.notchpay.co. Sans la clé de"
    echo "  signature, les webhooks sont refusés et aucun paiement n'est confirmé."
    NOTCHPAY_PUBLIC_KEY=$(sans_espace "Clé publique" "$(lire_ancien NOTCHPAY_PUBLIC_KEY)")
    NOTCHPAY_PRIVATE_KEY=$(secret_ou_conserver "Clé privée" NOTCHPAY_PRIVATE_KEY)
    NOTCHPAY_HASH_KEY=$(secret_ou_conserver "Clé de signature des webhooks" NOTCHPAY_HASH_KEY)
else
    vert "  paiements simulés : aucune clé NotchPay nécessaire"
fi

titre "Premier administrateur"
ADMIN_EMAIL=$(sans_espace "Adresse de l'administrateur" "$(lire_ancien ADMIN_EMAIL)")
ADMIN_PASSWORD=$(secret_ou_conserver "Mot de passe de l'administrateur" ADMIN_PASSWORD)

titre "Secrets"
# Conservés s'ils existent, engendrés sinon. Les réengendrer serait grave :
#   — DB_PASSWORD est déjà inscrit dans le volume de PostgreSQL, qui ne le
#     relit jamais : un nouveau mot de passe ferait refuser toute connexion ;
#   — APP_KEY chiffre les sessions et les jetons en base, qui deviendraient
#     indéchiffrables ;
#   — PUSHER_APP_KEY est compilée dans l'interface : il faudrait la rebâtir.
#
# « -hex » et non « -base64 » : ces valeurs voyagent dans des URL de connexion
# et dans des fichiers lus par le shell, où « / » et « + » posent problème.
engendrer() {          # engendrer <clé> <commande d'engendrement>
    local actuel
    actuel=$(lire_ancien "$1")
    if [ -n "$actuel" ]; then
        printf '%s' "$actuel"
        echo "  $1 : conservé" >&2
    else
        eval "$2"
        echo "  $1 : engendré" >&2
    fi
}

DB_PASSWORD=$(engendrer DB_PASSWORD 'openssl rand -hex 24')
PUSHER_APP_ID=$(engendrer PUSHER_APP_ID 'openssl rand -hex 8')
PUSHER_APP_KEY=$(engendrer PUSHER_APP_KEY 'openssl rand -hex 16')
PUSHER_APP_SECRET=$(engendrer PUSHER_APP_SECRET 'openssl rand -hex 24')
# APP_KEY : Laravel attend 32 octets en base64. « key:generate » ferait la
# même chose, mais elle exige PHP, absent de l'hôte.
APP_KEY=$(engendrer APP_KEY 'printf "base64:%s" "$(openssl rand -base64 32)"')

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
    echo "MAIL_PASSWORD='$MAIL_PASSWORD'"
    echo "MAIL_ENCRYPTION=$MAIL_ENCRYPTION"
    echo "MAIL_FROM_ADDRESS=$MAIL_FROM_ADDRESS"
    echo "MAIL_FROM_NAME=Reliance"
    # Facultative : boîte réellement relevée pour les réponses (conservée telle quelle).
    echo "MAIL_REPLY_TO_ADDRESS=$(lire_ancien MAIL_REPLY_TO_ADDRESS)"
    echo
    echo "NOTCHPAY_BASE_URL=https://api.notchpay.co"
    echo "NOTCHPAY_PUBLIC_KEY=$NOTCHPAY_PUBLIC_KEY"
    echo "NOTCHPAY_PRIVATE_KEY='$NOTCHPAY_PRIVATE_KEY'"
    echo "NOTCHPAY_HASH_KEY='$NOTCHPAY_HASH_KEY'"
    echo "PAIEMENT_SIMULE=$PREPRODUCTION"
    echo "PAIEMENT_SIMULE_DELAI_S=5"
    echo "PREPRODUCTION=$PREPRODUCTION"
    echo
    echo "SMS_DRIVER=log"
    echo "SMS_SENDER=Reliance"
    echo "EXPO_PUSH_URL=https://exp.host/--/api/v2/push/send"
    echo
    echo "REQUIRE_SUBSCRIPTION=false"
    echo "REQUIRE_2FA_CLIENTS=true"
    echo "SSE_MAX_SECONDS=300"
    echo "ADMIN_EMAIL=$ADMIN_EMAIL"
    echo "ADMIN_PASSWORD='$ADMIN_PASSWORD'"
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
