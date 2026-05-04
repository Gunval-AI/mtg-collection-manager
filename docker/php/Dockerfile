FROM php:8.3-apache

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo "upload_max_filesize=20M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=25M" >> /usr/local/etc/php/conf.d/uploads.ini

COPY . /var/www/html

RUN composer install --no-interaction --prefer-dist

COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/start.sh /usr/local/bin/start.sh

RUN chown -R www-data:www-data /var/www/html \
    && chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["start.sh"]