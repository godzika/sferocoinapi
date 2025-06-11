FROM php:8.2-fpm

# მოთხოვნადი extentions
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev curl \
    && docker-php-ext-install pdo pdo_pgsql zip

# Composer-ის გადმოწერა
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# სამუშაო დირექტორია
WORKDIR /var/www

# პროექტის ფაილების კოპირება
COPY . .

# სიმფონის cache/log-ს წვდომის უფლებები
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

# კომპოზერის ინსტალაცია პროდაქშენის რეჟიმში
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Default FPM command
CMD ["php-fpm"]

