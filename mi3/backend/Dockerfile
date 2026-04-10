FROM php:8.3-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring gd zip bcmath \
    && a2enmod rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# First: create a fresh Laravel 11 project as base
RUN composer create-project laravel/laravel:^11.0 /tmp/laravel --no-interaction --prefer-dist \
    && cp -r /tmp/laravel/* /tmp/laravel/.* /var/www/html/ 2>/dev/null || true \
    && rm -rf /tmp/laravel

# Install Sanctum
RUN composer require laravel/sanctum --no-interaction

# Now overlay our custom code on top of the Laravel base
COPY app/ /var/www/html/app/
COPY config/ /var/www/html/config/
COPY routes/ /var/www/html/routes/
COPY database/ /var/www/html/database/
COPY bootstrap/app.php /var/www/html/bootstrap/app.php
COPY public/index.php /var/www/html/public/index.php

# Copy env
COPY .env.example /var/www/html/.env

# Generate app key
RUN php artisan key:generate --force

# Optimize
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Apache config for Laravel on port 8080
RUN echo '<VirtualHost *:8080>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf

EXPOSE 8080

CMD ["apache2-foreground"]
