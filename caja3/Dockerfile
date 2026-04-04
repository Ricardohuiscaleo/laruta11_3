FROM php:8.4-apache

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
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf && \
    echo 'PassEnv APP_DB_HOST APP_DB_NAME APP_DB_USER APP_DB_PASS' >> /etc/apache2/apache2.conf && \
    echo 'PassEnv ADMIN_PASS CAJERA_PASS RICARDO_PASS MANAGER_PASS RUTA11_PASS' >> /etc/apache2/apache2.conf && \
    echo 'PassEnv TELEGRAM_TOKEN TELEGRAM_CHAT_ID' >> /etc/apache2/apache2.conf && \
    echo 'PassEnv GMAIL_CLIENT_ID GMAIL_CLIENT_SECRET GMAIL_SENDER_EMAIL' >> /etc/apache2/apache2.conf && \
    echo 'PassEnv TUU_API_KEY TUU_ONLINE_RUT TUU_ONLINE_SECRET TUU_ONLINE_ENV TUU_ENVIRONMENT TUU_DEVICE_SERIAL' >> /etc/apache2/apache2.conf && \
    echo 'PassEnv AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY S3_BUCKET S3_REGION S3_URL' >> /etc/apache2/apache2.conf

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
