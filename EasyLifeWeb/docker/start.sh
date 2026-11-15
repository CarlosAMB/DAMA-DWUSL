#!/bin/sh
# Arranque del contenedor en Render
set -e

# Variables obligatorias: si faltan se detiene con un mensaje claro (en vez de errores 500)
for var in APP_KEY DB_URL; do
  eval "value=\${$var:-}"
  if [ -z "$value" ]; then
    echo "ERROR: falta la variable de entorno $var. Agregala en Render > Environment y vuelve a desplegar." >&2
    exit 1
  fi
done
case "$APP_KEY" in
  base64:*) ;;
  *) echo "ERROR: APP_KEY debe empezar con 'base64:' (sin comillas ni espacios)." >&2; exit 1 ;;
esac

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

# Historial de demostracion (actividad desde septiembre): solo si se activa en Render.
# No duplica: si ya existe, el comando no hace nada.
if [ "${SEED_DEMO_HISTORY:-false}" = "true" ]; then
  php artisan app:demo-history
fi

exec apache2-foreground
