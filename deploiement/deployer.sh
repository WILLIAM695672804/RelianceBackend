#!/usr/bin/env bash
#
# Mise en service de Reliance sur le serveur partagé.
#
#     bash deploiement/deployer.sh            # construire, migrer, démarrer
#     bash deploiement/deployer.sh --semer    # idem, puis semences (première fois)
#     bash deploiement/deployer.sh --etat     # relevé seulement
#
# Idempotent : relançable sans dommage. Il s'arrête au premier échec en nommant
# la cause.
#
# Le frontal Caddy appartient à SASI et détient les ports 80 et 443. Ce script
# ne le redémarre jamais : il dépose le fichier de site et demande un
# rechargement, qui n'interrompt pas l'autre application.

set -euo pipefail

cd "$(dirname "$0")/.."        # racine de RelianceBackend

COMPOSITION="deploiement/docker-compose.production.yml"
RESEAU="frontal"
FRONTAL_RACINE="${FRONTAL_RACINE:-$HOME/AppSuivi/sasi-ia/config/proxy}"

rouge() { printf '\033[31m%s\033[0m\n' "$*" >&2; }
vert()  { printf '\033[32m%s\033[0m\n' "$*"; }
titre() { printf '\n\033[1m%s\033[0m\n' "$*"; }

composer_() { docker compose --env-file .env -f "$COMPOSITION" "$@"; }
frontal()   { docker compose -f "$FRONTAL_RACINE/docker-compose.yml" \
                  --env-file "$HOME/AppSuivi/sasi-ia/.env" "$@"; }

# ------------------------------------------------------------------ contrôles
verifier_prealables() {
    titre "Contrôles préalables"

    command -v docker >/dev/null || { rouge "Docker absent."; exit 1; }
    docker compose version >/dev/null 2>&1 || {
        rouge "Le greffon « docker compose » est absent."; exit 1; }
    vert "  Docker et docker compose présents"

    [ -f .env ] || {
        rouge "Fichier .env absent."
        echo "    bash deploiement/regler.sh"
        exit 1; }

    set -a; . ./.env; set +a
    local manquantes=()
    for nom in DOMAINE APP_KEY DB_PASSWORD PUSHER_APP_KEY PUSHER_APP_SECRET \
               MAIL_HOST NOTCHPAY_PRIVATE_KEY NOTCHPAY_HASH_KEY; do
        [ -n "${!nom:-}" ] || manquantes+=("$nom")
    done
    [ ${#manquantes[@]} -eq 0 ] || {
        rouge "Réglages manquants dans .env : ${manquantes[*]}"
        echo "    bash deploiement/regler.sh --forcer"
        exit 1; }

    # Le mot de passe voyage dans une URL de connexion : un « / » y clôt
    # l'autorité, et l'échec se produit loin de sa cause.
    case "$DB_PASSWORD" in
        *[/@:?\#\[\]%\ ]*)
            rouge "DB_PASSWORD contient un caractère interdit dans une URL."
            echo "    Engendrez-le ainsi : openssl rand -hex 24"
            exit 1;;
    esac
    vert "  .env complet — domaine $DOMAINE"

    # L'interface est compilée avec le code source du dépôt voisin.
    [ -d ../RelianceFrontend ] || {
        rouge "../RelianceFrontend est absent."
        echo "    cd .. && git clone -b devFrontend \\"
        echo "        https://github.com/WILLIAM695672804/RelianceFrontend.git"
        exit 1; }
    local branche
    branche=$(git -C ../RelianceFrontend rev-parse --abbrev-ref HEAD 2>/dev/null || echo inconnue)
    if [ "$branche" = devFrontend ]; then
        vert "  dépôt de l'interface présent, branche devFrontend"
    else
        rouge "  ../RelianceFrontend est sur « $branche », or la version à jour"
        rouge "  de l'interface vit sur « devFrontend » (main a 63 commits de retard)."
        echo "    git -C ../RelianceFrontend checkout devFrontend"
        echo "    Pour poursuivre malgré tout : PASSER_BRANCHE=1 $0"
        [ "${PASSER_BRANCHE:-}" = "1" ] || exit 1
    fi

    # Le certificat ne s'obtient que si le domaine désigne déjà cette machine.
    local attendue resolue
    attendue=$(curl -fsS --max-time 10 https://api.ipify.org 2>/dev/null || echo inconnue)
    resolue=$(getent hosts "$DOMAINE" 2>/dev/null | awk '{print $1; exit}')
    if [ -z "$resolue" ]; then
        rouge "  $DOMAINE ne se résout pas encore."
        echo "    Créez l'enregistrement A vers $attendue, puis attendez le TTL."
        echo "    Pour poursuivre malgré tout : PASSER_DNS=1 $0"
        [ "${PASSER_DNS:-}" = "1" ] || exit 1
    elif [ "$resolue" != "$attendue" ] && [ "$attendue" != inconnue ]; then
        rouge "  $DOMAINE pointe vers $resolue, or cette machine est $attendue."
        [ "${PASSER_DNS:-}" = "1" ] || exit 1
    else
        vert "  $DOMAINE → $resolue, c'est bien cette machine"
    fi
}

preparer_le_reseau() {
    titre "Réseau partagé"
    if docker network inspect "$RESEAU" >/dev/null 2>&1; then
        vert "  le réseau « $RESEAU » existe"
    else
        docker network create "$RESEAU" >/dev/null
        vert "  réseau « $RESEAU » créé"
    fi
}

construire_et_demarrer() {
    titre "Construction des images"
    echo "  La première fois : dix à vingt minutes. L'installation de composer"
    echo "  et la compilation de l'interface sont les deux étapes longues."
    composer_ build

    titre "Démarrage"
    composer_ up -d
    vert "  services démarrés"
}

attendre_la_base() {
    titre "Attente de la base"
    local n=0
    until composer_ exec -T base pg_isready -U "$DB_USERNAME" -d "$DB_DATABASE" >/dev/null 2>&1; do
        n=$((n + 1))
        [ "$n" -gt 60 ] && { rouge "La base ne répond pas après deux minutes."
                             composer_ logs --tail 20 base; exit 1; }
        sleep 2
    done
    vert "  la base accepte les connexions"
}

migrer() {
    titre "Migrations"
    # « --force » : sans lui, artisan refuse de migrer en production et attend
    # une confirmation que personne ne lui donnera dans un conteneur.
    composer_ run --rm -T api php artisan migrate --force
    vert "  schéma à jour"

    # PostGIS est créée par la migration des localisations. On le vérifie :
    # sans elle, le rapprochement géographique échouerait à la première requête.
    if composer_ exec -T base psql -U "$DB_USERNAME" -d "$DB_DATABASE" -tAc \
            "select 1 from pg_extension where extname='postgis'" 2>/dev/null | grep -q 1; then
        vert "  extension postgis en place"
    else
        rouge "  extension postgis absente : le rapprochement géographique échouera."
        exit 1
    fi
}

semer() {
    titre "Semences"
    composer_ run --rm -T api php artisan db:seed --force
    vert "  données initiales en place"
}

compiler_les_caches() {
    titre "Caches de configuration"
    # Dans cet ordre : vider d'abord, car un cache de l'image précédente
    # décrirait l'ancienne configuration et masquerait les nouveaux réglages.
    composer_ run --rm -T api php artisan config:clear
    composer_ run --rm -T api php artisan config:cache
    composer_ run --rm -T api php artisan route:cache
    composer_ run --rm -T api php artisan view:cache
    vert "  configuration, routes et vues compilées"

    # Les conteneurs tournent encore sur l'ancien cache : ils doivent le relire.
    composer_ restart api reverb planificateur >/dev/null
    vert "  services relancés sur la nouvelle configuration"
}

controler_la_production() {
    titre "Contrôle avant production"
    if composer_ run --rm -T api php artisan reliance:verifier-production; then
        vert "  configuration apte à la production"
    else
        rouge "  des réglages bloquants subsistent (voir ci-dessus)."
        echo "    Corrigez .env, puis relancez : bash deploiement/deployer.sh"
        exit 1
    fi
}

publier_le_site() {
    titre "Frontal partagé"
    if [ ! -d "$FRONTAL_RACINE/sites" ]; then
        rouge "  $FRONTAL_RACINE/sites est introuvable."
        echo "    Le frontal appartient à SASI. Indiquez son emplacement :"
        echo "    FRONTAL_RACINE=/chemin/vers/config/proxy $0"
        exit 1
    fi

    cp -f deploiement/reliance.caddy "$FRONTAL_RACINE/sites/reliance.caddy"
    vert "  sites/reliance.caddy déposé"

    # Un rechargement, jamais un redémarrage : le frontal sert aussi SASI.
    if frontal exec -T caddy caddy reload --config /etc/caddy/Caddyfile >/dev/null 2>&1; then
        vert "  frontal rechargé sans interruption"
    else
        rouge "  le rechargement a échoué. Détail :"
        frontal exec -T caddy caddy validate --config /etc/caddy/Caddyfile 2>&1 | tail -10
        exit 1
    fi
}

etat() {
    titre "État des services"
    composer_ ps

    titre "Certificat et accès"
    local code domaine="${DOMAINE:-}"
    if [ -n "$domaine" ]; then
        # Ce squelette n'a pas la route « /up » de Laravel 11 ; on interroge
        # celle que l'interface appelle de toute façon avant de se connecter.
        code=$(curl -sS --max-time 15 -o /dev/null -w '%{http_code}' \
                    "https://$domaine/sanctum/csrf-cookie" 2>/dev/null || echo 000)
        case "$code" in
            200|204) vert  "  HTTPS $code — certificat obtenu, application joignable" ;;
            000) rouge "  HTTPS : aucune réponse. Le certificat peut demander une minute." ;;
            502|503) rouge "  HTTPS $code — le frontal répond, mais pas l'application." ;;
            *)   rouge "  HTTPS $code — réponse inattendue" ;;
        esac
        echo "  Interface : https://$domaine"
    fi

    titre "Derniers journaux"
    composer_ logs --tail 8 api reverb planificateur 2>/dev/null || true
}

# ----------------------------------------------------------------------- corps
SEMER=0
case "${1:-}" in
    --etat)  set -a; . ./.env; set +a; etat; exit 0 ;;
    --semer) SEMER=1 ;;
    "")      ;;
    *)       rouge "Option inconnue : $1"; exit 1 ;;
esac

verifier_prealables
preparer_le_reseau
construire_et_demarrer
attendre_la_base
migrer
[ "$SEMER" = 1 ] && semer
compiler_les_caches
controler_la_production
publier_le_site
etat

titre "Terminé"
echo "  Interface : https://$DOMAINE"
echo "  Journaux  : docker compose --env-file .env -f $COMPOSITION logs -f"
