FROM php:8.2-apache

# Instalar Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    apt-get clean

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Copiar código
WORKDIR /var/www/html
COPY . .

# Instalar dependencias y build
RUN npm install && npm run build

# Configurar Apache para servir desde dist
RUN rm -rf /var/www/html && \
    mv dist /var/www/html

# Copiar archivos PHP (api, config, etc)
COPY api /var/www/html/api
COPY admin /var/www/html/admin
COPY load-env.php /var/www/html/

EXPOSE 80

CMD ["apache2-foreground"]
