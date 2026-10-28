# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.3-cli
ARG COMPOSER_VERSION=2

FROM composer:${COMPOSER_VERSION} AS composer-bin

FROM php:${PHP_VERSION}

# Install required system tools and libraries
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libtool \
    libzip-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PIE (PHP Installer for Extensions)
RUN curl -fsSL -o /usr/local/bin/pie https://github.com/php/pie/releases/latest/download/pie.phar \
    && chmod +x /usr/local/bin/pie

# Install core PHP extensions and third-party extensions via PIE
RUN docker-php-ext-install -j"$(nproc)" sockets zip \
    && pie install --no-interaction pecl/pcov \
    && rm -rf /root/.pie /tmp/pear

# Copy Composer binary from official Composer image
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

# Use development PHP configuration
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && { \
        echo "memory_limit = 512M"; \
        echo "date.timezone = UTC"; \
    } >> "$PHP_INI_DIR/conf.d/queue-bundle.ini"

# Set working directory and open Composer cache permissions for arbitrary user execution
WORKDIR /app
ENV COMPOSER_HOME=/tmp/composer
RUN mkdir -p /tmp/composer && chmod 1777 /tmp/composer

CMD ["composer", "test"]
