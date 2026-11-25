#!/bin/bash
set -e

cd /var/www/html

# Ensure writable directories (especially when thumbnails volume is mounted)
mkdir -p storage/app/public/thumbnails storage/app/public/admin-photos storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

# Harden admin-photos upload directory against script execution
cat > storage/app/public/admin-photos/.htaccess << 'EOF'
Options -Indexes -ExecCGI
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<FilesMatch "\.(?i:php[0-9]?|phtml|phar|pl|py|jsp|asp|aspx|sh|cgi|exe|bat|svg|html|htm)$">
    Require all denied
</FilesMatch>
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
</IfModule>
EOF

chown -R www-data:www-data storage bootstrap/cache

# Ensure public/storage symlink exists
if [ ! -L public/storage ]; then
    php artisan storage:link --force || true
fi

# Refresh Laravel caches for fast low-RAM execution
php artisan config:clear || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Start Laravel Scheduler in background (runs 08:00/08:15/08:20 & 20:00/20:15/20:20 WIB)
if [ "${RUN_SCHEDULER:-true}" = "true" ]; then
    su -s /bin/bash www-data -c "php /var/www/html/artisan schedule:work >> /var/www/html/storage/logs/scheduler.log 2>&1" &
fi

exec apache2-foreground
