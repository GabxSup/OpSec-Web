ARG PHP_VERSION=8.3.36
FROM php:${PHP_VERSION}-apache

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite headers

# Runtime hardening against CVE-2026-109618 (OOM corruption) and information disclosure
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "ServerTokens Prod" >> /etc/apache2/conf-available/security.conf \
    && echo "ServerSignature Off" >> /etc/apache2/conf-available/security.conf \
    && a2enconf security
