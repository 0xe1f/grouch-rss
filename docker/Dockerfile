FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# PHPUnit as a self-contained PHAR — no Composer needed
RUN curl -sL https://phar.phpunit.de/phpunit-11.phar -o /usr/local/bin/phpunit \
    && chmod +x /usr/local/bin/phpunit

WORKDIR /app
