FROM 1and1internet/php-build-environment:7.2 AS build
LABEL org.opencontainers.image.authors="Jessica Smith <jess@mintopia.net>"

WORKDIR /app/
USER 1000
ENV HOME /tmp

COPY --chown=1000:1000 . /app/
COPY --chown=1000:1000 .env /app/.env

RUN composer install \
    --no-progress \
    --optimize-autoloader \
    --prefer-dist

FROM mintopia/nginx-fpm-php:7.2
LABEL org.opencontainers.image.authors="Jessica Smith <jess@mintopia.net>"

USER root

RUN apk update \
    && apk --no-cache add \
        ${PHPIZE_DEPS} \
    && rm -rf /tmp/pear \
    && docker-php-ext-install \
        bcmath \
        pdo_mysql \
    && apk del --no-cache ${PHPIZE_DEPS} \
    && rm -vrf /tmp/pear /var/cache/apk/* \
    && mkdir -p /app /tmp \
    && chown -R 1000:1000 /app /tmp

WORKDIR /var/www/
USER 1000
ENV HOME /tmp
COPY --from=build --chown=1000:1000 /app/ /var/www/
