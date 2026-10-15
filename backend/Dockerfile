FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev libcurl4-openssl-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql curl mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

RUN { \
        echo '[www]'; \
        echo 'clear_env = no'; \
    } > /usr/local/etc/php-fpm.d/zz-env.conf

EXPOSE 9000
