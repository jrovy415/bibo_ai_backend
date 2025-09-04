# Use official PHP with FPM
FROM php:8.2-fpm

# Arguments for UID/GID so container matches host user
ARG user=laravel
ARG uid=1000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev mariadb-client \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (better layer caching)
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev, optimized autoload)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy rest of the application
COPY . .

# Run Laravel scripts (vendor:publish, key:generate handled by your scripts)
RUN composer dump-autoload --optimize

# Create a non-root user (for security)
RUN useradd -G www-data,root -u $uid -d /home/$user $user \
    && mkdir -p /home/$user/.composer \
    && chown -R $user:$user /var/www/html /home/$user

USER $user

# Expose port 8000
EXPOSE 8000

# Start Laravel (Render will inject $PORT)
CMD php artisan serve --host=0.0.0.0 --port=$PORT
