#!/usr/bin/env bash

if [ ! -f "vendor/bin/phpcs" ]; then
    echo "PHPCS no encontrado. Ejecuta: composer install"
    exit 1
fi

echo "Ejecutando PHPCS..."
./vendor/bin/phpcs
PHPCS_EXIT=$?

# Exit 0 = sin problemas, 1 = solo warnings (permitido), 2 = errores (bloqueante).
if [ "$PHPCS_EXIT" -gt 1 ]; then
    echo "PHPCS: errores encontrados. Commit bloqueado."
    exit "$PHPCS_EXIT"
fi

echo "PHPCS: sin errores."
