#!/usr/bin/env bash

if [ ! -f "vendor/bin/phpcs" ]; then
    echo "PHPCS no encontrado. Ejecuta: composer install"
    exit 1
fi

echo "Ejecutando PHPCS..."
./vendor/bin/phpcs
# Comprobación de errores reales (ignorando warnings):
# PHPCS exit 1 significa cualquier violación (warnings O errors).
# Ejecutamos de nuevo sin warnings para saber si hay errores reales.
./vendor/bin/phpcs --warning-severity=0 --no-colors -q > /dev/null 2>&1
PHPCS_ERRORS=$?

if [ "$PHPCS_ERRORS" -ne 0 ]; then
    echo "PHPCS: errores encontrados. Commit bloqueado."
    exit 1
fi

echo "PHPCS: sin errores."
