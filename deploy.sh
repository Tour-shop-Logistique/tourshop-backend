#!/bin/bash

# Script de déploiement automatique pour AWS EC2
# Assurez-vous d'avoir les droits d'exécution : chmod +x deploy.sh

echo "🚀 Démarrage du déploiement..."

# 1. Récupérer la dernière version du code
echo "📥 Récupération du code depuis Git..."
git pull origin main

# 2. Reconstruire et démarrer les conteneurs
echo "🏗️ Reconstruction des images Docker..."
docker-compose up -d --build

# 3. Installer les dépendances PHP (uniquement en production)
echo "📦 Installation des dépendances Composer..."
docker-compose exec -T app composer install --no-dev --optimize-autoloader
docker-compose exec app php artisan key:generate

# 4. Fixer les permissions des dossiers de stockage
echo "🔑 Configuration des permissions (storage/cache)..."
docker-compose exec -T app chmod -R 777 storage bootstrap/cache

# 5. Exécuter les migrations de la base de données
# echo "🗄️ Exécution des migrations..."
# docker-compose exec -T app php artisan migrate --force

# 6. Optimiser Laravel pour la production
echo "⚡ Optimisation du cache Laravel..."
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
docker-compose exec -T app php artisan view:cache

echo "✅ Déploiement terminé avec succès !"
