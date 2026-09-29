# GatewayPro - imagem do APP
#
# Faltava no pacote original: so havia docker/Dockerfile.db (o banco).
# Este arquivo monta o app em cima do PHP+Apache.
#
# O banco e a imagem separada (docker/Dockerfile.db, MariaDB 10.11).

FROM php:8.2-apache

# Extensoes que o codigo realmente usa:
#   pdo_mysql  - todo o acesso ao banco
#   curl       - Mercado Pago, UTMfy, Evolution API, webhooks
#   mbstring   - acentos e nomes
#   simplexml  - 1 arquivo usa
#   zip        - 1 arquivo usa
#   fileinfo   - 1 arquivo usa
#   opcache    - performance
# Nota: nao rode "docker-php-ext-configure oniguruma" aqui. No PHP 8.2 o
# oniguruma ja vem embutido no mbstring e nao existe como extensao separada
# na imagem oficial - esse comando falha o build.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libonig-dev libcurl4-openssl-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql curl mbstring simplexml zip fileinfo opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# .htaccess precisa de AllowOverride All para as URLs limpas (/checkout em vez
# de /checkout.php). Sem isso o app abre a pagina de listagem de arquivos.
RUN printf '<Directory /var/www/html>\n    Options -Indexes +FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/gatewaypro.conf \
    && a2enconf gatewaypro

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-gatewaypro.ini

WORKDIR /var/www/html

# Codigo do app
COPY . /var/www/html

# O entrypoint gera o config/config.php a partir das variaveis de ambiente
COPY docker/entrypoint.sh /usr/local/bin/gatewaypro-entrypoint.sh
RUN chmod +x /usr/local/bin/gatewaypro-entrypoint.sh

# Logs do PHP precisam existir antes do php.ini apontar para eles
RUN mkdir -p /var/log && touch /var/log/php_errors.log \
    && chown www-data:www-data /var/log/php_errors.log

# uploads/ e config/ precisam existir e ser gravaveis pelo Apache.
# O mkdir e obrigatorio: o .dockerignore tira o conteudo de uploads/, e sem
# o diretorio o chown abaixo quebraria o build.
RUN mkdir -p /var/www/html/uploads /var/www/html/config /var/www/html/logs_backup \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/config /var/www/html/logs_backup \
    && chmod -R 775 /var/www/html/uploads /var/www/html/config /var/www/html/logs_backup

ENTRYPOINT ["gatewaypro-entrypoint.sh"]
CMD ["apache2-foreground"]
