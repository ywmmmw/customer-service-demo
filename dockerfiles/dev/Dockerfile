# Default Dockerfile
#
# @link     https://www.hyperf.io
# @document https://hyperf.wiki
# @contact  group@hyperf.io
# @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE

FROM hyperf/hyperf:7.4-alpine-v3.11-cli
LABEL maintainer="ywmmmw <ywmmmw@icoud.com>" version="1.0" license="MIT" app.name="HyperfChatDemo"

##
# ---------- env settings ----------
##
# --build-arg timezone=Asia/Shanghai
ARG timezone

ENV TIMEZONE=${timezone:-"Asia/Shanghai"} \
    COMPOSER_VERSION=2.0.3 \
    APP_ENV=dev \
    SCAN_CACHEABLE=(false)

# update
RUN set -ex \
    # install composer
    && cd /tmp \
    && wget https://github.com/composer/composer/releases/download/${COMPOSER_VERSION}/composer.phar \
    && chmod u+x composer.phar \
    && mv composer.phar /usr/local/bin/composer \
    && composer config -g repo.packagist composer https://mirrors.aliyun.com/composer \
    # show php version and extensions
    && php -v \
    && php -m \
    && php --ri swoole \
    #  ---------- some config ----------
    && cd /etc/php7 \
    # - config PHP
    && { \
        echo "upload_max_filesize=128M"; \
        echo "post_max_size=128M"; \
        echo "memory_limit=1G"; \
        echo "date.timezone=${TIMEZONE}"; \
    } | tee conf.d/99_overrides.ini \
    # - config timezone
    && ln -sf /usr/share/zoneinfo/${TIMEZONE} /etc/localtime \
    && echo "${TIMEZONE}" > /etc/timezone \
    # ---------- clear works ----------
    && rm -rf /var/cache/apk/* /tmp/* /usr/share/man \
    && echo -e "\033[42;37m Build Completed :).\033[0m\n"

# WORKDIR /opt/www

# Composer Cache
# COPY ./composer.* /opt/www/
# RUN composer install --no-dev --no-scripts

# COPY . /opt/www
# RUN composer install --no-dev -o && php bin/hyperf.php

EXPOSE 9501
EXPOSE 9502

ENTRYPOINT ["/bin/sh"]
