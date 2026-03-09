# Menggunakan PHP 8.4 dengan Apache
FROM php:8.4-apache

# Menginstal library sistem yang dibutuhkan Laravel dan SQLite
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Menginstal ekstensi PHP
RUN docker-php-ext-install pdo pdo_sqlite

# Mengaktifkan Apache Mod Rewrite (.htaccess)
RUN a2enmod rewrite

# Mengubah DocumentRoot Apache ke folder /public Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Menginstal Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Menyiapkan direktori kerja
WORKDIR /var/www/html
COPY . .

# ---> TAMBAHKAN BARIS INI <---
RUN composer install --no-dev --optimize-autoloader

# Mengatur hak akses folder (tambahkan folder vendor juga)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/vendor