#!/bin/bash

# Script de déploiement automatique pour AWS EC2
# Assurez-vous d'avoir les droits d'exécution : chmod +x deploy.sh

echo "🚀 Démarrage du déploiement..."
echo ""

# 1. Récupérer la dernière version du code
echo "📥 1- Récupération du code depuis Git..."
git pull origin main
echo ""

# 2. Reconstruire et démarrer les conteneurs
echo "🏗️ 2- Reconstruction des images Docker..."
docker-compose up -d --build
echo ""

# 3. Installer les dépendances PHP (uniquement en production)
echo "📦 3- Installation des dépendances Composer..."
docker-compose exec app composer install
echo ""

# 4. Fixer les permissions des dossiers de stockage
echo "🔑 4- Configuration des permissions (storage/cache)..."
docker-compose exec app chmod -R 777 storage bootstrap/cache
echo ""

# 5. Exécuter les migrations de la base de données
# echo "🗄️ Exécution des migrations..."
# docker-compose exec -T app php artisan migrate --force

# 6. Optimiser Laravel pour la production
echo "⚡ 5- Optimisation du cache Laravel..."
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
echo ""
echo "✅ Déploiement terminé avec succès !"
