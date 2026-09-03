FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

# System packages: PHP 8.3 CLI + extensions required by the app
# (dompdf/simple-qrcode -> gd, phpspreadsheet -> zip/gd/xml, sqlite driver, etc.),
# Node.js 22 for Vite 7, Composer, and common build tooling.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl git unzip gnupg \
        php-cli \
        php-mbstring \
        php-xml \
        php-curl \
        php-zip \
        php-gd \
        php-bcmath \
        php-sqlite3 \
        php-intl \
        php-gmp \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet \
    && rm -f /tmp/composer-setup.php \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

USER ubuntu
