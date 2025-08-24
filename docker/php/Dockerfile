FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git zip unzip libicu-dev libonig-dev libxml2-dev \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libpq-dev libmcrypt-dev libcurl4-openssl-dev \
    curl libssl-dev libxslt-dev gnupg \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install PHP extensions
RUN docker-php-ext-install intl pdo pdo_mysql opcache zip gd

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html/sagit

# Copy Symfony app into the image
COPY . .

# Install Composer dependencies
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

# Generate autoload files
RUN composer dump-autoload --optimize


# Set permissions (if necessary)
RUN mkdir -p /var/www/html/sagit/var && \
    chown -R www-data:www-data /var/www/html/sagit/var
RUN chmod -R 775 /var/www/html/sagit/var

# Expose port 9000
EXPOSE 9000
CMD ["php-fpm"]



