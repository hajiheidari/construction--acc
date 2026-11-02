# Development/build tools only; production runs prebuilt PHP artifacts on DirectAdmin.
FROM php:8.4-cli-bookworm
RUN --mount=type=secret,id=proxy_ca,required=true \
    SSL_CERT_FILE=/run/secrets/proxy_ca apt-get update && \
    SSL_CERT_FILE=/run/secrets/proxy_ca apt-get install -y --no-install-recommends git unzip libonig-dev libzip-dev libicu-dev libsqlite3-dev && \
    docker-php-ext-install -j2 bcmath mbstring pdo_mysql pdo_sqlite intl zip && \
    rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /workspace/construction--acc
RUN --mount=type=secret,id=proxy_ca,required=true \
    SSL_CERT_FILE=/run/secrets/proxy_ca apt-get update && \
    SSL_CERT_FILE=/run/secrets/proxy_ca apt-get install -y --no-install-recommends libpng-dev && \
    docker-php-ext-install -j2 gd && rm -rf /var/lib/apt/lists/*
