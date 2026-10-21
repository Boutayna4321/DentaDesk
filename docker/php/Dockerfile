FROM php:8.4-cli

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl zip pcntl bcmath opcache
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} app && useradd -m -u ${UID} -g ${GID} app
USER app
WORKDIR /var/www/app
