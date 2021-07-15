FROM php:8.0-cli-alpine3.13

ENV GOOGLE_APPLICATION_CREDENTIALS=/app/service-account.json

RUN docker-php-ext-install mysqli \
     && mkdir /app \
     && cd /app \
     && curl -o composer-setup.php https://getcomposer.org/installer \
     && php composer-setup.php \
     && rm composer-setup.php

WORKDIR /app

ADD . /app

RUN php composer.phar install

CMD ["php", "updateCalendar.php"]
