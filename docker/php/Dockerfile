FROM php:8.3-fpm

# --- Argumentos para alinear el usuario del contenedor con el host (evita problemas de permisos) ---
ARG UID=1000
ARG GID=1000

# --- Dependencias del sistema ---
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# --- Extensiones PHP requeridas por Laravel ---
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# --- Extensión Redis (PECL) ---
RUN pecl install redis \
    && docker-php-ext-enable redis

# --- Configuración PHP personalizada (memory_limit, uploads, etc.) ---
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-portal.ini

# --- Composer (copiado desde la imagen oficial) ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Node.js 20 + npm (para assets del frontend) ---
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# --- Crear un usuario no-root que coincida con el host ---
RUN groupadd -g ${GID} appuser \
    && useradd -u ${UID} -g appuser -m -s /bin/bash appuser

WORKDIR /var/www/html

USER appuser

EXPOSE 9000

CMD ["php-fpm"]
