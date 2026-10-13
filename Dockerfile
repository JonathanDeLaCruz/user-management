ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli
RUN apt-get update && apt-get install -y libzip-dev libpng-dev libonig-dev unzip git && docker-php-ext-install pdo_mysql mbstring gd zip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
CMD ["php", "-S", "0.0.0.0:8080", "-t", "demo/web", "demo/web/router.php"]
