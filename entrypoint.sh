#!/bin/bash
set -e

# Ensure storage dirs exist
mkdir -p storage/app/public
chmod -R 775 storage bootstrap/cache

# If public/storage is a real directory, remove it
if [ -d public/storage ] && [ ! -L public/storage ]; then
  rm -rf public/storage
fi

# Create symlink (ignore error if it already exists)
php artisan storage:link || true

# Run migrations automatically (optional — comment out if not desired)
php artisan migrate --force || true

# Cache config for performance
php artisan config:cache || true

# Finally, start Laravel’s dev server
exec php artisan serve --host=0.0.0.0 --port=8000
