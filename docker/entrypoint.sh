#!/bin/bash

# Nettoyer les caches pour éviter les problèmes de déploiement
echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Exécuter les migrations (si nécessaire)
# echo "Running migrations..."
# php artisan migrate --force

# Démarre Apache en premier plan
apache2-foreground
