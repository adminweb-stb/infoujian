FROM php:8.3-fpm

# Install mysqli & opcache extensions
# mysqli is required for the database connection
# opcache is highly recommended for production performance
RUN docker-php-ext-install mysqli opcache && \
    docker-php-ext-enable mysqli opcache

# Set working directory to match the Nginx configuration
WORKDIR /var/www/html
