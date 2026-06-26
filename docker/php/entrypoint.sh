#!/bin/sh

chown -R www-data:www-data /app/storage /app/bootstrap/cache 2>/dev/null || true
chmod -R 775 /app/storage /app/bootstrap/cache 2>/dev/null || true

mkdir -p /app/storage/logs
chown -R www-data:www-data /app/storage/logs
chmod -R 775 /app/storage/logs

# Run artisan as www-data so any log files (storage/logs/laravel.log) are
# created owned by www-data — otherwise php-fpm workers (which drop to
# www-data) cannot append to a root-owned log and every request returns 500.
su-exec www-data php artisan migrate --force || true
su-exec www-data php artisan optimize:clear || true

exec supervisord -c /etc/supervisord.conf
