# Build stage
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Production stage
FROM php:8.2-apache

# Instalar extensiones PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Configurar Apache
RUN a2enmod rewrite headers && \
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copiar build desde stage anterior
COPY --from=builder /app/dist /var/www/html
COPY api /var/www/html/api
COPY public/load-env.php /var/www/html/
COPY public/config.php /var/www/html/

# Permisos
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

WORKDIR /var/www/html
EXPOSE 80
CMD ["apache2-foreground"]
