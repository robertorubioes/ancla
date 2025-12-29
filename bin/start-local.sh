#!/bin/bash
#------------------------------------------------------------------------------
# ANCLA - Local Development Server Start Script
#------------------------------------------------------------------------------
# Usage: ./bin/start-local.sh
#
# This script starts the local development server on ancla.local:8000
#
# Prerequisites:
#   1. Add to /etc/hosts: 127.0.0.1 ancla.local
#      Run: echo "127.0.0.1 ancla.local" | sudo tee -a /etc/hosts
#
#   2. Ensure PHP 8.2+ is installed with required extensions
#
# Access:
#   App: http://ancla.local:8000
#------------------------------------------------------------------------------

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

# Check if hosts entry exists
if ! grep -q "ancla.local" /etc/hosts 2>/dev/null; then
    echo "⚠️  WARNING: ancla.local not found in /etc/hosts"
    echo "   Run: echo '127.0.0.1 ancla.local' | sudo tee -a /etc/hosts"
    echo ""
fi

# Check if .env exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.example..."
    cp .env.example .env
    php artisan key:generate
fi

# Check if database exists
if [ ! -f database/database.sqlite ]; then
    echo "📦 Creating SQLite database..."
    touch database/database.sqlite
    php artisan migrate --force
fi

# Clear and optimize
echo "🔧 Optimizing..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Start the server
echo ""
echo "🚀 Starting ANCLA development server..."
echo ""
echo "   ┌─────────────────────────────────────────────┐"
echo "   │  App URL: http://ancla.local:8000           │"
echo "   │  Verify:  http://ancla.local:8000/verify    │"
echo "   │  API:     http://ancla.local:8000/api/v1/   │"
echo "   └─────────────────────────────────────────────┘"
echo ""
echo "   Press Ctrl+C to stop the server"
echo ""

php artisan serve --host=0.0.0.0 --port=8000
