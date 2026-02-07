# Utilisation de l'image PHP 8.1 FPM
FROM php:8.1-fpm

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip

# Nettoyage du cache apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Installation des extensions PHP pour Laravel et PostgreSQL
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définition du dossier de travail
WORKDIR /var/www

# Par défaut, Docker tournera en tant que 'root'