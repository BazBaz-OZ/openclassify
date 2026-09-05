#!/bin/sh
set -e

if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
    php artisan key:generate --force
fi

php artisan migrate --force
# php artisan db:seed --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan filament:assets
php artisan livewire:publish --assets
php artisan storage:link

# Laravel startup commands run as root and may create runtime directories.
# Make sure PHP-FPM (www-data) can create listing media, logs and cache files.
mkdir -p /var/www/html/storage/app/public/public
mkdir -p /var/www/html/bootstrap/cache
# Runtime/cache files may be created or removed during Laravel startup.
# A disappearing cache file must never prevent the container from starting.
chown -R www-data:www-data     /var/www/html/storage     /var/www/html/bootstrap/cache     2>/dev/null || true

chmod -R ug+rwX     /var/www/html/storage     /var/www/html/bootstrap/cache     2>/dev/null || true

# Nginx workers run as www-data and need access to request temp storage.
mkdir -p /var/lib/nginx/tmp/client_body
chown -R www-data:www-data /var/lib/nginx
chmod 750 /var/lib/nginx
chmod 700 /var/lib/nginx/tmp
chmod 700 /var/lib/nginx/tmp/client_body

php-fpm -D
nginx -g 'daemon off;'
