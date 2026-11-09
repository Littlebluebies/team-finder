# Frontend build
FROM node:22-alpine AS frontend
WORKDIR /src/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# Backend composer deps
FROM composer:2 AS backend
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader
COPY backend/ ./

# Runtime: nginx + php-fpm in one container
FROM php:8.2-fpm-alpine
RUN apk add --no-cache nginx
RUN docker-php-ext-install pdo_mysql opcache

WORKDIR /app
COPY --from=backend /app /app/backend
COPY --from=frontend /src/frontend/dist /app/frontend/dist
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && mkdir -p /app/backend/runtime /app/backend/web/assets \
    && chown -R www-data:www-data /app/backend/runtime /app/backend/web/assets

ENV APP_URL="" \
    DRY_RUN="false" \
    MAIL_DSN="" \
    MAIL_FROM="" \
    MAIL_FROM_NAME="Team Finder" \
    LINE_CHANNEL_ID="" \
    LINE_CHANNEL_SECRET="" \
    LINE_ACCESS_TOKEN="" \
    LINE_REDIRECT_URI=""

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
