FROM php:8.2-cli-alpine

# Install system dependencies and PHP extensions.
# sqlite-dev is needed to compile pdo_sqlite.
# icu-dev is needed for the intl extension (Symfony translator component).
RUN apk add --no-cache \
        sqlite-dev \
        icu-dev \
        unzip \
    && docker-php-ext-install \
        pdo_sqlite \
        intl \
        opcache

# Copy Composer binary from the official Composer image.
# Avoids running the Composer installer script and keeps the layer cacheable.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy the entrypoint script first so it is available even before
# the full source is mounted via a docker-compose volume.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
