# hadolint global ignore=DL3018
ARG PHP_VERSION=8.3
FROM uselagoon/php-${PHP_VERSION}-cli-drupal:26.8.1

RUN apk add --no-cache $PHPIZE_DEPS && \
    pecl install pcov && \
    docker-php-ext-enable pcov

# The PHP 8.5 image enables opcache.huge_code_pages on a kernel that offers no
# huge pages, so every process starts by writing a warning to stdout. Drupal
# runs each kernel test in its own process and parses that stdout, where the
# warning corrupts the result payload and fails the test.
RUN echo "opcache.huge_code_pages=0" > /usr/local/etc/php/conf.d/zz-opcache-huge-code-pages.ini
