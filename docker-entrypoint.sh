#!/bin/bash
set -e

echo "Starting application setup..."

# Wait for database to be ready (optional, uncomment if needed)
# echo "Waiting for database..."
# while ! php artisan db:monitor > /dev/null 2>&1; do
#     sleep 1
# done

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force
fi

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Run seeders
echo "Running seeders..."
php artisan db:seed --force

# Cache configuration
echo "Caching configuration..."
php artisan config:cache

# Cache routes
echo "Caching routes..."
php artisan route:cache

# Publish Filament assets
echo "Publishing Filament assets..."
php artisan filament:assets || true

# Cache views (skip if error)
echo "Caching views..."
php artisan view:cache || true

# Create storage link
echo "Creating storage link..."
php artisan storage:link || true

# Set permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Clear application cache
echo "Clearing application cache..."
php artisan cache:clear

echo "Setup completed successfully!"

# Test Nginx configuration
echo "Testing Nginx configuration..."
nginx -t

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
php-fpm -D

# Start Nginx in foreground
echo "Starting Nginx..."
nginx -g "daemon off;"

