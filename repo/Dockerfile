FROM php:8.3-cli-bookworm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libzip-dev libpng-dev \
    libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev \
    postgresql-client wait-for-it \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring zip gd bcmath xml pcntl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Node 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock* ./

# Install PHP dependencies (no dev in prod, but we need dev for testing)
RUN composer install --no-interaction --no-scripts --prefer-dist

# Copy package files for npm layer caching
COPY package.json package-lock.json* vite.config.js ./

# Install Node dependencies (npm install used since package-lock.json is generated at build time)
RUN npm install

# Copy application source
COPY . .

# Run composer post-install scripts
RUN composer run-script post-autoload-dump 2>/dev/null || true

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 8000 8080

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
