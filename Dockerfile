FROM php:8.0.13-cli-buster

RUN apt-get update && apt-get -y install libxml2-dev git && apt-get clean

RUN docker-php-ext-install soap

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" && \
	curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
