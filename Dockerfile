# the different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/compose/compose-file/#target

ARG PHP_VERSION=8.4
ARG ALPINE_VERSION=3.22
ARG COMPOSER_VERSION=2.8
ARG PHP_EXTENSION_INSTALLER_VERSION=latest
ARG APP_USER=symfony

FROM composer:${COMPOSER_VERSION} AS composer

FROM mlocati/php-extension-installer:${PHP_EXTENSION_INSTALLER_VERSION} AS php_extension_installer

FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION} AS base

ARG APP_USER=symfony

# persistent / runtime deps
RUN apk add --no-cache \
        acl \
        file \
        gettext \
        unzip \
        shadow \
    ;

COPY --from=php_extension_installer /usr/bin/install-php-extensions /usr/local/bin/

# default PHP image extensions
# ctype curl date dom fileinfo filter ftp hash iconv json libxml mbstring mysqlnd openssl pcre PDO pdo_sqlite Phar
# posix readline Reflection session SimpleXML sodium SPL sqlite3 standard tokenizer xml xmlreader xmlwriter zlib
RUN install-php-extensions apcu exif gd intl pdo_pgsql opcache zip

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php/prod/php.ini   $PHP_INI_DIR/php.ini

# Create application user
RUN set -eux; \
    addgroup -g 1000 ${APP_USER}; \
    adduser -u 1000 -G ${APP_USER} -h /home/${APP_USER} -s /bin/sh -D ${APP_USER}

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN set -eux; \
    composer clear-cache

# Set working directory to user's home/app
WORKDIR /home/${APP_USER}/app

# Update PATH for the user
ENV PATH="${PATH}:/home/${APP_USER}/.composer/vendor/bin"

# Copy entrypoint script (as root)
COPY --link --chmod=755  docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

# Change ownership of the working directory
RUN chown -R ${APP_USER}:${APP_USER} /home/${APP_USER}

# Switch to application user
USER ${APP_USER}

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]




FROM base AS app_php_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off

# Switch to root for system configuration
USER root

COPY docker/php/dev/xdebug.ini   $PHP_INI_DIR/conf.d/xdebug.ini

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Switch back to application user
ARG APP_USER=symfony
USER ${APP_USER}


#RUN apk add --update linux-headers
#RUN apk add --no-cache $PHPIZE_DEPS \
#    && pecl install xdebug-3.4.6 \
#    && docker-php-ext-enable xdebug







FROM base AS app_prod

ARG APP_USER=symfony

ENV APP_ENV=prod

# Switch to root for system configuration
USER root
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Generate app.prod.ini from template with APP_USER substitution
COPY docker/php/conf.d/app.prod.ini.template /tmp/app.prod.ini.template
RUN envsubst '${APP_USER}' < /tmp/app.prod.ini.template > "$PHP_INI_DIR/conf.d/app.prod.ini" && \
    rm /tmp/app.prod.ini.template

# Switch back to application user
USER ${APP_USER}

# prevent the reinstallation of vendors at every changes in the source code
COPY --link composer.* symfony.* ./

RUN set -eux; \
    composer install --prefer-dist --no-autoloader --no-interaction --no-scripts --no-progress --no-dev; \
    composer clear-cache

# copy only specifically what we need
COPY .env .env.prod ./
COPY assets assets/
COPY bin bin/
COPY config config/
COPY public public/
COPY src src/
COPY templates templates/
COPY translations translations/

RUN set -eux; \
	mkdir -p var/cache var/log; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; sync;
