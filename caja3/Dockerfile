FROM php:8.2-apache

# Instalar Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    apt-get clean

# Instalar extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite y configurar Apache
RUN a2enmod rewrite headers && \
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copiar código
WORKDIR /app
COPY . .

# Instalar dependencias y build
RUN npm install && npm run build

# Mover build a Apache
RUN rm -rf /var/www/html/* && \
    cp -r dist/* /var/www/html/ && \
    cp -r api /var/www/html/ && \
    [ -d admin ] && cp -r admin /var/www/html/ || true && \
    cp load-env.php /var/www/html/ && \
    cp config.php /var/www/html/

WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
