# Use PHP 8.2-FPM as base image
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20.x
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application directory permissions
RUN chown -R www-data:www-data /var/www

# Copy application files
COPY --chown=www-data:www-data . /var/www

# Install PHP dependencies
USER www-data
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Install NPM dependencies and build assets
RUN npm ci && npm run build

# Publish Filament assets
RUN php artisan filament:assets --force || true

# Switch back to root for nginx configuration
USER root

# Copy Nginx configuration
COPY nginx/conf.d/app.conf /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/sites-enabled/default

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Expose port 80
EXPOSE 80

# Use entrypoint script
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

