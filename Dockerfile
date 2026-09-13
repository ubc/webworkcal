# composer.lock requires PHP >= 8.1 (monolog 3); keep this in step with
# config.platform.php in composer.json.
FROM php:8.3-cli-alpine

ENV GOOGLE_APPLICATION_CREDENTIALS=/app/service-account.json

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN docker-php-ext-install mysqli && mkdir /app

WORKDIR /app

ADD . /app

RUN composer install --no-dev --no-interaction --no-progress

CMD ["php", "updateCalendar.php"]
