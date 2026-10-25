FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-server \
    mariadb-client \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli pdo pdo_mysql gd zip \
    && a2enmod rewrite headers setenvif \
    && rm -rf /var/lib/apt/lists/*

ENV MYSQLHOST=127.0.0.1 \
    MYSQLDATABASE=tp_planner \
    MYSQLUSER=tp_planner \
    MYSQLPASSWORD=tp_planner \
    MYSQLPORT=3306

RUN echo "upload_max_filesize = 20M\npost_max_size = 20M\nmemory_limit = 256M" > /usr/local/etc/php/conf.d/uploads.ini

COPY sites/ /var/www/
RUN mv /var/www/tp-planner/uploads /var/www/tp-planner/uploads_seed \
    && chown -R www-data:www-data /var/www/

COPY db/mysql_backup.sql /docker-entrypoint-initdb.d/mysql_backup.sql
COPY apache-sites.conf /etc/apache2/sites-available/000-default.conf
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
