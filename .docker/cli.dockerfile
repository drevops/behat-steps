# hadolint global ignore=DL3018
ARG PHP_VERSION=8.3
FROM uselagoon/php-${PHP_VERSION}-cli-drupal:26.9.0

RUN apk add --no-cache $PHPIZE_DEPS && \
    pecl install pcov && \
    docker-php-ext-enable pcov

# Overwrites the file of the same name that the base image ships, so the
# setting is the same on every platform the image runs on.
COPY .docker/php/zz-opcache-huge-code-pages.ini /usr/local/etc/php/conf.d/zz-opcache-huge-code-pages.ini
