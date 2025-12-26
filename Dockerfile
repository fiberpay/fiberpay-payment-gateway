FROM wordpress:php8.2-apache

WORKDIR /var/www/html

RUN set -eux; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        bash \
        ca-certificates \
        curl \
        less \
        unzip; \
    rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    curl -sSLo /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; \
    chmod +x /usr/local/bin/wp

COPY --chown=www-data:www-data . /var/www/html/wp-content/plugins/fiberpay-payment-gateway

COPY docker/fiberpay-entrypoint.sh /usr/local/bin/fiberpay-entrypoint.sh

RUN set -eux; \
    chmod +x /usr/local/bin/fiberpay-entrypoint.sh

RUN set -eux; \
    find /var/www/html/wp-content/plugins/fiberpay-payment-gateway -type d -exec chmod 755 {} \;; \
    find /var/www/html/wp-content/plugins/fiberpay-payment-gateway -type f -exec chmod 644 {} \;

ENTRYPOINT ["/usr/local/bin/fiberpay-entrypoint.sh"]

CMD ["apache2-foreground"]
