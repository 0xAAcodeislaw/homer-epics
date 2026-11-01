FROM php:8.3-apache

RUN a2enmod rewrite && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY . /var/www/html

ARG SITE_URL="https://homer-epics.0xaa-codeislaw.workers.dev"
# 管理员密码不在源码中：由部署脚本经 --build-arg 传入，缺省时构建期随机生成，
# 写入镜像 /root/admin-password.txt（webroot 之外），静态镜像不暴露任何后台
ARG TYPECHO_USER_PASSWORD=""

RUN cd /var/www/html && \
    PASS="${TYPECHO_USER_PASSWORD:-$(openssl rand -hex 10)}" && \
    echo "$PASS" > /root/admin-password.txt && \
    TYPECHO_SITE_URL=${SITE_URL} \
    TYPECHO_DB_ADAPTER=Pdo_SQLite \
    TYPECHO_DB_PREFIX=typecho_ \
    TYPECHO_DB_FILE=/var/www/html/usr/homer.db \
    TYPECHO_USER_NAME=admin \
    TYPECHO_USER_PASSWORD="$PASS" \
    TYPECHO_USER_MAIL=admin@homer-epics.local \
    php install.php && \
    SITE_URL=${SITE_URL} php scripts/seed.php && \
    rm -f install.php && \
    php -r 'file_put_contents("/var/www/html/usr/installed.lock", "1");' && \
    chown -R www-data:www-data /var/www/html/usr

EXPOSE 80
