FROM php:8.5-apache

# Install system dependencies and PHP extensions required by Laravel + MySQL
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite for Laravel routing
RUN a2enmod rewrite

# Point Apache DocumentRoot to /public and allow .htaccess overrides
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV PORT=10000

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && echo 'Listen ${PORT}' > /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# Raise PHP upload limits for 5MB portfolio images and CV files
RUN { \
      echo "upload_max_filesize=10M"; \
      echo "post_max_size=12M"; \
      echo "memory_limit=256M"; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first for better Docker layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy application source
COPY . .

# Run package discovery and ensure writable Laravel directories exist
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache/data \
                storage/framework/sessions \
                storage/framework/views \
                storage/logs \
                bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 10000

# Cache runtime Render env vars on boot, run migrations, then start Apache
CMD sh -c "php artisan config:cache && php artisan migrate --force && exec apache2-foreground"
