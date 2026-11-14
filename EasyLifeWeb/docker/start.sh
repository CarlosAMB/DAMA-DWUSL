#!/bin/sh
# Arranque del contenedor en Render
set -e

# Apache escucha en el puerto que asigna Render
sed -i "s/Listen 80$/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf

# Cache de configuracion/rutas/vistas (lee las variables de entorno de Render)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Base de datos: aplica migraciones y, si esta vacia, carga los datos iniciales (admin, demo, catalogos)
php artisan migrate --force
php artisan app:seed-if-empty

exec apache2-foreground
