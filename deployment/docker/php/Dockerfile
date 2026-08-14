# 1. THE BASE IMAGE
FROM php:8.2-fpm-alpine

# 2. SYSTEM DEPENDENCIES (Required for Composer & PHP extensions + Nginx for Staging Unified Compute)
RUN apk add --no-cache \
    libzip-dev \
    zip \
    unzip \
    nginx

# 3. THE EXTENSIONS
RUN docker-php-ext-install pdo pdo_mysql zip

RUN docker-php-ext-install bcmath

# COMPILING NATIVE PHPREDIS EXTENSION
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# 4. PRODUCTION HARDENING
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 5. ENVIRONMENT CONFIG
# Forces PHP to read the .env file values into the $_ENV superglobal
RUN echo 'variables_order = "EGPCS"' > $PHP_INI_DIR/conf.d/00-env-vars.ini

# Hardens the engine by permanently stripping the x-powered-by version header
RUN echo 'expose_php = Off' > $PHP_INI_DIR/conf.d/00-security.ini

# 6. WORKSPACE
WORKDIR /var/www/html

# 7. UNIFIED STAGING LAYER BOUNDS
# Copy application code straight into the container workspace to build a standalone artifact
COPY . /var/www/html

# Link your custom Nginx virtual host configuration rule directly into Alpine's default directory
# (standalone.conf: nginx + php-fpm share this one container, so upstream is 127.0.0.1:9000)
COPY ./deployment/docker/nginx/standalone.conf /etc/nginx/http.d/default.conf

# Expose the public HTTP router port natively
EXPOSE 80

# parallel background process daemon execution sequence
CMD php-fpm -D && nginx -g "daemon off;"