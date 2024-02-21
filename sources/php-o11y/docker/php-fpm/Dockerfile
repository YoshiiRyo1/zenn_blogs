FROM php:8.3.2-fpm-bullseye
WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y \
  git gcc make autoconf unzip zlib1g-dev \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* \
  && pecl install opentelemetry \
  && pecl install grpc \
  && docker-php-ext-enable opentelemetry grpc

COPY ./html/ /var/www/html/

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer init \
  --no-interaction \
  --require slim/slim:"^4" \
  --require slim/psr7:"^1" \
  && composer update \
  && composer config allow-plugins.php-http/discovery false \
  && composer require \
  open-telemetry/sdk \
  open-telemetry/opentelemetry-auto-slim \
  open-telemetry/exporter-otlp \
  php-http/guzzle7-adapter \
  grpc/grpc:^1.38 \
  open-telemetry/transport-grpc \
  monolog/monolog \
  open-telemetry/opentelemetry-logger-monolog
