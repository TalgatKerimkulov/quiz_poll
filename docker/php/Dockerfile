FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    git \
    curl \
    zip \
    unzip \
    supervisor \
    su-exec

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    gd \
    zip \
    mbstring \
    exif \
    pcntl \
    bcmath \
    opcache

# Install phpredis (PECL) — required for CACHE_STORE=redis / REDIS_CLIENT=phpredis
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Add custom PHP configuration overrides
COPY ./docker/php/overrides.ini /usr/local/etc/php/conf.d/99-overrides.ini

# Set working directory
WORKDIR /app

COPY . .

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN mkdir -p storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN composer install --ignore-platform-req=ext-sockets --no-interaction --prefer-dist --optimize-autoloader --no-plugins --no-scripts

# Expose port
EXPOSE 9000

# Add the entrypoint script and supervisor config
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php/supervisord.conf /etc/supervisord.conf
RUN chmod +x /usr/local/bin/entrypoint.sh

# Change CMD to use the entrypoint script
CMD ["entrypoint.sh"]
