# Utilisation de l'image PHP 8.1 avec Apache intégré
FROM php:8.1-apache

# 1. Activation des modules Apache
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
    curl \
    libpq-dev

# 3. Nettoyage du cache apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 4. Installation des extensions PHP indispensables pour Laravel et PostgreSQL
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# 5. Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Configuration du dossier racine d'Apache pour pointer sur le dossier 'public' de Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 6b. Configuration du port Apache pour Render (écoute sur le port défini par PORT, par défaut 80 ou 10000)
# Render définit dynamiquement le port via la variable d'environnement PORT (souvent 10000)
RUN sed -s -i 's/80/${PORT}/g' /etc/apache2/conf-available/docker-php.conf /etc/apache2/sites-available/*.conf
RUN sed -s -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf

# 7. Définition du dossier de travail
WORKDIR /var/www/html

# 8. Copie du code dans le conteneur
COPY . .

# 9. Installation des dépendances et fix des permissions
RUN composer install --no-interaction --optimize-autoloader --no-dev
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# 10. Script d'entrée
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# 11. Exposer le port (Render l'utilisera)
EXPOSE 80

# 12. Commande de démarrage
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]