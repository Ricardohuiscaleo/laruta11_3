FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev unzip git curl \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring gd zip bcmath pcntl \
    && a2enmod rewrite headers

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Create Laravel base
RUN composer create-project laravel/laravel:^11.0 /tmp/laravel --no-interaction --prefer-dist --no-scripts \
    && cp -a /tmp/laravel/. /var/www/html/ \
    && rm -rf /tmp/laravel

# Install project-specific dependencies
RUN composer require laravel/sanctum:^4.0 minishlink/web-push:^9.0 laravel/reverb:^1.0 pusher/pusher-php-server:^7.2 league/flysystem-aws-s3-v3:^3.0 --no-interaction --no-scripts --optimize-autoloader

# Overlay custom code
COPY app/ /var/www/html/app/
COPY config/ /var/www/html/config/
COPY routes/ /var/www/html/routes/
COPY database/migrations/ /var/www/html/database/migrations/
COPY database/seeders/ /var/www/html/database/seeders/
COPY bootstrap/app.php /var/www/html/bootstrap/app.php

# Supervisor config for Apache + Reverb
COPY docker/supervisord.conf /etc/supervisord.conf
RUN mkdir -p /var/log/supervisor

# Empty .env — Coolify injects env vars at runtime (including APP_KEY)
RUN rm -f .env && touch .env

# NOTE: key:generate removed — APP_KEY is injected by Coolify as persistent env var
# Running key:generate here would generate a new key on each build, invalidating cookies

# Dump autoload with our custom classes
RUN composer dump-autoload --optimize --no-scripts

# Clear caches
RUN php artisan config:clear && php artisan route:clear

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache

# Apache on port 8080 with WebSocket proxy to Reverb on 9090
RUN a2enmod proxy proxy_http proxy_wstunnel

RUN printf '<VirtualHost *:8080>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    \n\
    # WebSocket proxy: /app/* -> Reverb on port 9090\n\
    ProxyPreserveHost On\n\
    ProxyPass /app ws://127.0.0.1:9090/app\n\
    ProxyPassReverse /app ws://127.0.0.1:9090/app\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
