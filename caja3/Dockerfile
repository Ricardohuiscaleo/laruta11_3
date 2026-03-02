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

# Invalidar cache para forzar rebuild
ARG CACHEBUST=1
RUN echo "Cache bust: $CACHEBUST"

COPY . .

# Build caja3
RUN npm install && npm run build

# Build admin2
RUN cd admin2 && npm install && npm run build

# Mover build a Apache
RUN rm -rf /var/www/html/* && \
    cp -r dist/* /var/www/html/ && \
    cp -r api /var/www/html/ && \
    mkdir -p /var/www/html/admin2 && \
    cp -r admin2/dist/* /var/www/html/admin2/ && \
    cp admin2-htaccess /var/www/html/admin2/.htaccess && \
    cp load-env.php /var/www/html/ && \
    cp config.php /var/www/html/ && \
    cp ws-server.js /var/www/html/ && \
    cp package.json /var/www/html/ && \
    cp package-lock.json /var/www/html/

# Instalar dependencias de producción para WebSocket
RUN cd /var/www/html && npm install --production

WORKDIR /var/www/html

EXPOSE 80 8080

# Iniciar Apache y WebSocket
CMD node ws-server.js & apache2-foreground
