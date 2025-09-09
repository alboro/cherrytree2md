FROM php:8.4-cli-alpine

# Install required extensions and dev packages
RUN apk add --no-cache \
    sqlite \
    sqlite-dev \
    && docker-php-ext-install pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy application
COPY . .

# Make binary executable
RUN chmod +x bin/ctb2md

# Create output directory
RUN mkdir -p /output

# Default command
ENTRYPOINT ["php", "bin/ctb2md"]
CMD ["--help"]
