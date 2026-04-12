FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring gd zip bcmath \
    && a2enmod rewrite headers

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Create Laravel base WITHOUT running post-install scripts (avoids migrate in production)
RUN composer create-project laravel/laravel:^11.0 /tmp/laravel --no-interaction --prefer-dist --no-scripts \
    && cp -a /tmp/laravel/. /var/www/html/ \
    && rm -rf /tmp/laravel

# Copy project composer files BEFORE install to get our dependencies (web-push, etc.)
COPY composer.json /var/www/html/composer.json

# Install dependencies including project-specific ones (no scripts to avoid artisan calls during build)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Overlay custom code
COPY app/ /var/www/html/app/
COPY config/ /var/www/html/config/
COPY routes/ /var/www/html/routes/
COPY database/migrations/ /var/www/html/database/migrations/
COPY database/seeders/ /var/www/html/database/seeders/
COPY bootstrap/app.php /var/www/html/bootstrap/app.php

# Empty .env — Coolify injects env vars at runtime
RUN rm -f .env && touch .env

# Generate APP_KEY for build (will be overridden by Coolify env var at runtime)
RUN php artisan key:generate --force --no-interaction

# Dump autoload with our custom classes
RUN composer dump-autoload --optimize --no-scripts

# Clear and cache config/routes for production
RUN php artisan config:clear && php artisan route:clear

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache

# Apache on port 8080
RUN printf '<VirtualHost *:8080>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf

EXPOSE 8080

CMD ["apache2-foreground"]
