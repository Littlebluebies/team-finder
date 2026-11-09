#!/bin/sh
set -e

cd /app/backend

# Apply pending migrations on every deploy.
php yii migrate --interactive=0

# Retry failed notification deliveries once a minute, in the background.
(
    mkdir -p runtime/logs
    while true; do
        php yii notification/retry >> runtime/logs/retry.log 2>&1 || true
        sleep 60
    done
) &

exec nginx -g 'daemon off;'
