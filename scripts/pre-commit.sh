#!/usr/bin/env bash
set -e

if [ ! -f "vendor/bin/phpcs" ]; then
    echo "PHPCS no encontrado. Ejecuta: composer install"
    exit 1
fi

echo "Ejecutando PHPCS..."
./vendor/bin/phpcs
echo "PHPCS: sin errores."
