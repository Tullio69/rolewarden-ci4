# S3 (Collaudatore ad Hoc): official php:<v>-apache image, plus what CodeIgniter 4 and the installer list
# (intl, mysqli; mbstring and json are built in), mod_rewrite, Composer for the module-alone runs.
ARG PHPV=8.5
FROM php:${PHPV}-apache
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev unzip git \
 && docker-php-ext-install intl mysqli && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers \
 && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
# error reporting at its maximum; PHP's own errors to a file read by the suite
RUN printf 'error_reporting = -1\nlog_errors = On\nerror_log = /var/log/php-errors.log\ndisplay_errors = Off\ndisplay_startup_errors = On\nsendmail_path = /bin/false\n' > /usr/local/etc/php/conf.d/zz-s2.ini \
 && touch /var/log/php-errors.log && chown www-data /var/log/php-errors.log
