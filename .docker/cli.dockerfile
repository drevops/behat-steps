# hadolint global ignore=DL3018
FROM uselagoon/php-8.3-cli-drupal:25.7.0

RUN apk add --no-cache $PHPIZE_DEPS && \
    pecl install pcov && \
    docker-php-ext-enable pcov
