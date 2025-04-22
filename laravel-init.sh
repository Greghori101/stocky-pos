#!/bin/bash

composer install
php artisan key:generate
php artisan migrate --force
chown -R www-data:www-data /var/www/html
chmod -R 775 storage bootstrap/cache
