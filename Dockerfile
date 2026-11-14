FROM php:8.4-cli-alpine

# Install system dependencies for PHP extensions (minimal for CI)
RUN apk add --no-cache \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    git \
    zip \
    unzip

# Install essential PHP extensions for Laravel CI
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Default command for CI (override as needed)
CMD ["sh"]
