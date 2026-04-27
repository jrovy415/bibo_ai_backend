#!/bin/bash
set -e

# Ensure storage dirs exist
mkdir -p storage/app/public
mkdir -p storage/logs
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
chmod -R 775 storage bootstrap/cache

# If public/storage is a real directory, remove it
if [ -d public/storage ] && [ ! -L public/storage ]; then
  rm -rf public/storage
fi

# Create symlink (ignore error if it already exists)
php artisan storage:link || true

# Clear old cache first
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true

# Run migrations automatically
php artisan migrate --force || true

# Cache config for performance
php artisan config:cache || true
php artisan route:cache || true

# Start Laravel server
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}