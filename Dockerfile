FROM php:8.2-apache

# Install system deps required by PHP extensions (mbstring needs oniguruma), then extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring fileinfo \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite (for .htaccess support)
RUN a2enmod rewrite

# PHP configuration overrides
COPY docker/php.ini /usr/local/etc/php/conf.d/owere.ini

# Document root is /var/www/html — the project is mounted there via docker-compose
WORKDIR /var/www/html