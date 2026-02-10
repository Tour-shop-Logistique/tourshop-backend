#!/bin/bash

# Mettre en cache la configuration et les routes
echo "Caching config..."
php artisan config:cache
echo "Caching routes..."
php artisan route:cache

# Exécuter les migrations (si nécessaire)
# echo "Running migrations..."
# php artisan migrate --force

# Démarre Apache en premier plan
apache2-foreground
