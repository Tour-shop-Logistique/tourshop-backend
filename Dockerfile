# Utilisation de l'image PHP 8.1 avec Apache intégré
FROM php:8.1-apache

# 1. Activation du module de réécriture d'Apache (nécessaire pour les routes Laravel)
RUN a2enmod rewrite

# 2. Installation des dépendances système (Git, Zip, PostgreSQL, etc.)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    curl

# 3. Nettoyage du cache apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 4. Installation des extensions PHP indispensables pour Laravel et PostgreSQL
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# 5. Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Configuration du dossier racine d'Apache pour pointer sur le dossier 'public' de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 7. Définition du dossier de travail
WORKDIR /var/www/html

# 8. Copie du code dans le conteneur
COPY . .

# 9. Installation des dépendances et fix des permissions
RUN composer install --no-interaction --optimize-autoloader --no-dev
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# # 10. Exposer le port 80
# EXPOSE 80

# # 11. Commande de démarrage
# CMD ["apache2-foreground"]