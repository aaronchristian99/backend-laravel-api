# Base image
FROM php:7.4-apache

# Set the working directory
WORKDIR /var/www/html

# Set environment variable for non-interactive session
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copy the project files to the container
COPY . /var/www/html

# Install dependencies
RUN apt-get update && \
    apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip pdo_mysql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install project dependencies
RUN composer install

RUN composer update

# Run migrations and seeds
RUN php artisan migrate --seed

# Start the server
CMD ["php", "artisan", "serve", "--host=0.0.0.0"]
