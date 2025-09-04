# Use official PHP image
FROM php:8.2-cli

# Set working directory
WORKDIR /var/www

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git curl zip unzip libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip

# Install Composer (from Composer's official image)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the entire Laravel project into the container
COPY . .

# Install dependencies without dev packages (for production)
RUN composer install --no-dev --optimize-autoloader

# Set proper permissions (important for Laravel's storage/logs)
RUN chmod -R 775 storage bootstrap/cache

# Expose Laravel default port
EXPOSE 8000

# Start Laravel server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
