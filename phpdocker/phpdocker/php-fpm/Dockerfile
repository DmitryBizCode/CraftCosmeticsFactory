FROM phpdockerio/php:8.3-fpm
WORKDIR "/application"

# Установка расширений PHP
RUN apt-get update && apt-get install -y \
    php8.3-mysqli \
    php8.3-pdo \
    php8.3-pdo-mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
