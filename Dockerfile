FROM php:8.3-apache

RUN a2enmod rewrite && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY . /var/www/html

ARG SITE_URL="https://homer-epics.0xaa-codeislaw.workers.dev"

RUN cd /var/www/html && \
    TYPECHO_SITE_URL=${SITE_URL} \
    TYPECHO_DB_ADAPTER=Pdo_SQLite \
    TYPECHO_DB_PREFIX=typecho_ \
    TYPECHO_DB_FILE=/var/www/html/usr/homer.db \
    TYPECHO_USER_NAME=admin \
    TYPECHO_USER_PASSWORD=REDACTED \
    TYPECHO_USER_MAIL=admin@homer-epics.local \
    php install.php && \
    SITE_URL=${SITE_URL} php scripts/seed.php && \
    rm -f install.php && \
    php -r 'file_put_contents("/var/www/html/usr/installed.lock", "1");' && \
    chown -R www-data:www-data /var/www/html/usr

EXPOSE 80
