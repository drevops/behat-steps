# hadolint global ignore=DL3018
FROM uselagoon/php-8.2-cli-drupal:25.3.0

RUN apk add --no-cache $PHPIZE_DEPS && \
    pecl install pcov && \
    docker-php-ext-enable pcov
