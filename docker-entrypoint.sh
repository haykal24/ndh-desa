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

# Clear all caches first (important after code changes)
echo "Clearing all caches..."
php artisan cache:clear || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Cache configuration (after clearing)
echo "Caching configuration..."
php artisan config:cache

# Cache routes (after clearing)
echo "Caching routes..."
php artisan route:cache

# Publish Filament assets
echo "Publishing Filament assets..."
php artisan filament:assets || true

# Publish Livewire assets
echo "Publishing Livewire assets..."
php artisan livewire:publish --assets || true

# Cache views (skip if error)
echo "Caching views..."
php artisan view:cache || true

# Create storage link
echo "Creating storage link..."
php artisan storage:link || true

# Create upload directories if they don't exist
echo "Creating upload directories..."
mkdir -p /var/www/storage/app/public/uploads/desa
mkdir -p /var/www/storage/app/public/uploads/desa/logo

# Set permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Clear permission cache (important for Spatie Permission)
echo "Clearing permission cache..."
php artisan permission:cache-reset || true

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

