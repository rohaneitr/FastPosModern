# ── PHP 8.4 with SQLite + Composer baked in ─────────────────────────────────
FROM php:8.4-cli

# Install system packages
RUN apt-get update -qq && \
    apt-get install -y --no-install-recommends \
        libsqlite3-dev unzip curl git libgmp-dev libzip-dev libpq-dev \
    && docker-php-ext-install pdo pdo_sqlite pcntl bcmath gmp zip pdo_pgsql pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

# Copy composer files first for Docker layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev, optimised autoloader)
RUN composer install --no-interaction --no-dev --optimize-autoloader --no-scripts

# Copy the full application
COPY . .

# Run post-install scripts
RUN composer dump-autoload --optimize

# Create the storage directory for the SQLite database file.
# The database/ directory (with migrations) stays in the image;
# only the SQLite FILE is stored in a named volume at /var/db/database.sqlite
RUN mkdir -p /var/db

EXPOSE 8000

# On every container start:
#  1. Create the sqlite file if it doesn't exist
#  2. Clear config cache (so env vars from docker-compose are picked up)
#  3. Run fresh migrations + seeders
#  4. Start the built-in PHP dev server
CMD bash -c "\
    touch /var/db/database.sqlite && \
    php artisan config:clear && \
    php artisan migrate:fresh --seed --force && \
    php -S 0.0.0.0:8000 -t public \
"
