#!/bin/bash

# ANCLA - Auto Fix Script
# Este script ejecuta las herramientas de calidad de código automáticamente.

echo "⚓️ Iniciando Protocolo de Mantenimiento Autónomo ANCLA..."

# 1. Laravel Pint (Estilo)
if [ -f ./vendor/bin/pint ]; then
    echo "🎨 Ejecutando Laravel Pint..."
    ./vendor/bin/pint
else
    echo "⚠️ Laravel Pint no encontrado. Saltando..."
fi

# 2. Rector (Refactorización)
if [ -f ./vendor/bin/rector ]; then
    echo "🔧 Ejecutando Rector..."
    ./vendor/bin/rector process
else
    echo "⚠️ Rector no encontrado. Saltando..."
fi

# 3. Larastan (Análisis Estático)
if [ -f ./vendor/bin/phpstan ]; then
    echo "🔍 Ejecutando PHPStan..."
    ./vendor/bin/phpstan analyse
else
    echo "⚠️ PHPStan no encontrado. Saltando..."
fi

echo "✅ Mantenimiento finalizado."
