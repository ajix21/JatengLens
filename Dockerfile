FROM php:8.2-apache

# Install system dependencies for Laravel, DomPDF, Maatwebsite Excel, and MySQL
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        intl \
        bcmath \
        opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Set timezone to WIB (Asia/Jakarta)
ENV TZ=Asia/Jakarta
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Point Apache DocumentRoot to Laravel public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Low-RAM Apache MPM Prefork tuning for 4GB Homelab (~80-120MB RAM)
RUN printf '<IfModule mpm_prefork_module>\n\
    StartServers            2\n\
    MinSpareServers         2\n\
    MaxSpareServers         4\n\
    MaxRequestWorkers       20\n\
    MaxConnectionsPerChild  500\n\
</IfModule>\n' > /etc/apache2/mods-available/mpm_prefork.conf

# PHP & OPcache production tuning for low RAM
RUN printf 'memory_limit=192M\n\
upload_max_filesize=20M\n\
post_max_size=24M\n\
max_execution_time=120\n\
date.timezone=Asia/Jakarta\n\
opcache.enable=1\n\
opcache.memory_consumption=64\n\
opcache.interned_strings_buffer=8\n\
opcache.max_accelerated_files=10000\n\
opcache.validate_timestamps=0\n' > /usr/local/etc/php/conf.d/99-homelab-prod.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first (layer caching)
COPY sociowatch-jateng/composer.json sociowatch-jateng/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy application source
COPY sociowatch-jateng/ ./
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/app/public/thumbnails storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Entrypoint (runs storage:link, optimize, background scheduler, and Apache)
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
