FROM php:8.3-fpm-alpine

# Extensions PHP
RUN apk add --no-cache \
        libpq-dev libldap openldap-dev icu-dev libxml2-dev libzip-dev \
        openssh-client python3 py3-pip curl git unzip \
    && docker-php-ext-configure ldap \
    && docker-php-ext-install \
        pdo pdo_pgsql intl xml zip bcmath opcache ldap \
    && pecl install redis ssh2-1.4.1 \
    && docker-php-ext-enable redis ssh2

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# psutil pour l'agent (utilisé dans les scripts de collecte)
RUN pip3 install psutil --break-system-packages 2>/dev/null || pip3 install psutil

WORKDIR /var/www/obstack

# Copier les sources
COPY . .

# Dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Répertoires et permissions
RUN mkdir -p var/cache var/log /var/lib/obstack/.ssh \
    && chown -R www-data:www-data var/ \
    && chmod 700 /var/lib/obstack/.ssh

# Générer la clé SSH agent si absente
RUN ssh-keygen -t rsa -b 4096 -N '' -f /var/lib/obstack/.ssh/id_rsa \
    -C "obstack-agent@docker" 2>/dev/null || true

# Opcache
RUN echo "opcache.enable=1\nopcache.memory_consumption=256\nopcache.max_accelerated_files=20000\nopcache.validate_timestamps=0" \
    >> /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 9000

CMD ["php-fpm"]
