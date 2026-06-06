FROM php:8.3-cli

# Install curl extension (json is built-in since PHP 8.0)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev zip unzip git \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Copy composer binary from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
