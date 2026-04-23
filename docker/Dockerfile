# Digital Signature Factory - Docker Image
# مصنع التوقيع الرقمي - صورة Docker

FROM php:8.2-apache

LABEL maintainer="Digital Signature Factory AI <support@dsfactory.ai>"
LABEL version="1.0.0"
LABEL description="Complete Digital Signature Management System for Egyptian & Gulf Government Portals"

# Set environment variables
ENV DEBIAN_FRONTEND=noninteractive
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    openssl \
    libengine-pkcs11-openssl \
    softhsm2 \
    opensc \
    pcscd \
    pcsc-tools \
    pkcs11-tool \
    libpcsclite-dev \
    libccid \
    libssl-dev \
    libcurl4-openssl-dev \
    zlib1g-dev \
    libicu-dev \
    g++ \
    unzip \
    nodejs \
    npm \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && a2enmod rewrite ssl headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && mkdir -p ${APACHE_DOCUMENT_ROOT}

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create directories
RUN mkdir -p /var/log/dsfactory \
    /var/www/html/storage \
    /var/www/html/tokens \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure SoftHSM
RUN mkdir -p /etc/softhsm \
    && echo "directories.tokendir = /var/lib/softhsm/tokens/" > /etc/softhsm/softhsm2.conf \
    && mkdir -p /var/lib/softhsm/tokens \
    && chmod 755 /var/lib/softhsm/tokens

# Copy application files
WORKDIR /var/www/html
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader \
    && composer dump-autoload --optimize

# Install frontend dependencies
WORKDIR /var/www/html/frontend
RUN npm install \
    && npm run build

# Return to app root
WORKDIR /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/log/dsfactory

# Expose ports
EXPOSE 80 443

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start services
CMD ["sh", "-c", "service pcscd start && apache2-foreground"]
