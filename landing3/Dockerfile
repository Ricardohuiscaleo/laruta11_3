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
WORKDIR /app
COPY . .

# Instalar dependencias y build
RUN npm install && npm run build

# Mover build a Apache
RUN rm -rf /var/www/html/* && \
    cp -r dist/* /var/www/html/ && \
    cp -r api /var/www/html/ && \
    cp load-env.php /var/www/html/ && \
    [ -d vendor ] && cp -r vendor /var/www/html/ || true

WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
