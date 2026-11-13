# Image PHP de Reliance : elle sert quatre rôles — php-fpm derrière nginx, le
# serveur Reverb, le planificateur, et les commandes artisan de mise en service.
#
# Construite sur le serveur (ARM64) : rien n'est à envoyer depuis le poste de
# développement, dont la connexion ne le supporterait pas.

FROM php:8.3-fpm-alpine

# Bibliothèques de construction, retirées ensuite : seules les bibliothèques
# partagées restent dans l'image.
RUN apk add --no-cache --virtual .build \
        $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev oniguruma-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev \
    && apk add --no-cache \
        postgresql-libs icu-libs libzip oniguruma \
        freetype libjpeg-turbo libpng tzdata \
    # pdo_pgsql : la base est PostgreSQL, PostGIS y est exigé.
    # pcntl     : Reverb en dépend pour ses signaux ; sans lui il refuse de démarrer.
    # intl      : formats de dates et de montants (FCFA).
    # gd        : rendu PNG des codes QR de la double authentification.
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql pcntl intl bcmath zip gd opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Les dépendances d'abord, le code ensuite : une modification du code ne refait
# pas l'installation, qui prend plusieurs minutes sur deux cœurs ARM.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && rm -rf tests .github docs

COPY deploiement/php.ini /usr/local/etc/php/conf.d/reliance.ini

# www-data existe déjà dans l'image (uid 82). Les quatre conteneurs partagent le
# volume « storage » : ils doivent tourner sous le même compte, sinon un fichier
# écrit par le planificateur n'est plus modifiable par php-fpm — et le dépôt
# d'une pièce jointe échoue sans que rien ne l'explique.
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
