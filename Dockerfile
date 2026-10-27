# Alpine cli is used for cli only. Better for packages dev not requiring web servers interactions
FROM php:8.4-cli-alpine

# Dependencies and extensions
RUN apk add --no-cache \
    unzip \
    git \
    oniguruma-dev \
    && docker-php-ext-install mbstring

# Copy composer executable
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app