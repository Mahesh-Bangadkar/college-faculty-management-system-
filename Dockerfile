FROM php:8.2-apache

# Install dependencies and pdo_mysql
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev zip unzip git \
    && docker-php-ext-install pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Ensure uploads directory exists and is writable
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 80

CMD ["apache2-foreground"]
