# Deployment Guide - TourShop Backend

## 🚀 Vue d'ensemble

Ce guide couvre le déploiement complet de l'application TourShop en production, de la configuration de l'infrastructure à la mise en ligne sécurisée.

## 📋 Prérequis

### Environnement Technique
- **PHP**: 8.1+ avec extensions requises
- **Base de données**: PostgreSQL 14+ ou MySQL 8.0+
- **Serveur Web**: Nginx ou Apache
- **Cache**: Redis 6+
- **Queue**: Redis ou Beanstalkd
- **SSL**: Certificat HTTPS obligatoire

### Extensions PHP Requises
```bash
php-fpm
php-cli
php-mbstring
php-xml
php-curl
php-zip
php-gd
php-json
php-pgsql  # ou php-mysql
php-redis
php-bcmath
php-intl
php-opcache
```

## 🏗️ Architecture de Déploiement

### Options d'Hébergement

#### 1. Cloud Providers (Recommandé)
- **AWS**: EC2 + RDS + ElastiCache + S3
- **Azure**: App Service + Azure Database + Redis Cache
- **Google Cloud**: Compute Engine + Cloud SQL + Memorystore
- **DigitalOcean**: Droplets + Managed Database + Redis

#### 2. PaaS (Plus Simple)
- **Laravel Forge**: Configuration automatisée
- **Vapor**: Serverless Laravel
- **Heroku**: Déploiement simplifié
- **Ploi**: Gestion de serveurs optimisés

#### 3. Serveur Dédié/VPS
- **Ubuntu 20.04+** ou **CentOS 8+**
- Configuration manuelle complète
- Contrôle total de l'infrastructure

## 🔧 Configuration du Serveur

### 1. Installation des Dépendances

#### Ubuntu/Debian
```bash
# Mise à jour système
sudo apt update && sudo apt upgrade -y

# Installation PHP et extensions
sudo apt install php8.1 php8.1-fpm php8.1-cli php8.1-mbstring php8.1-xml php8.1-curl php8.1-zip php8.1-gd php8.1-json php8.1-pgsql php8.1-redis php8.1-bcmath php8.1-intl php8.1-opcache -y

# Installation Nginx
sudo apt install nginx -y

# Installation PostgreSQL
sudo apt install postgresql postgresql-contrib -y

# Installation Redis
sudo apt install redis-server -y

# Installation Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Installation Node.js (pour assets)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs -y
```

#### CentOS/RHEL
```bash
# Installation EPEL
sudo yum install epel-release -y

# Installation PHP
sudo yum install php81 php81-fpm php81-cli php81-mbstring php81-xml php81-curl php81-zip php81-gd php81-json php81-pgsql php81-redis php81-bcmath php81-intl php81-opcache -y

# Installation Nginx
sudo yum install nginx -y

# Installation PostgreSQL
sudo yum install postgresql-server postgresql-contrib -y
sudo postgresql-setup initdb
```

### 2. Configuration PostgreSQL

```bash
# Sécurisation PostgreSQL
sudo -u postgres psql
CREATE DATABASE tourshop;
CREATE USER tourshop_user WITH PASSWORD 'votre_mot_de_passe_complexe';
GRANT ALL PRIVILEGES ON DATABASE tourshop TO tourshop_user;
\q

# Configuration PostgreSQL
sudo nano /etc/postgresql/14/main/postgresql.conf
# Modifier: listen_addresses = 'localhost'

sudo nano /etc/postgresql/14/main/pg_hba.conf
# Ajouter: local   tourshop   tourshop_user   md5

sudo systemctl restart postgresql
sudo systemctl enable postgresql
```

### 3. Configuration Nginx

```nginx
# /etc/nginx/sites-available/tourshop
server {
    listen 80;
    server_name votre-domaine.com www.votre-domaine.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name votre-domaine.com www.votre-domaine.com;

    root /var/www/tourshop/public;
    index index.php index.html index.htm;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/votre-domaine.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/votre-domaine.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Laravel Configuration
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Static Files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Security
    location ~ /\.ht {
        deny all;
    }

    # File Upload Limits
    client_max_body_size 50M;
}
```

### 4. Configuration SSL avec Let's Encrypt

```bash
# Installation Certbot
sudo apt install certbot python3-certbot-nginx -y

# Génération certificat
sudo certbot --nginx -d votre-domaine.com -d www.votre-domaine.com

# Auto-renewal
sudo crontab -e
# Ajouter: 0 12 * * * /usr/bin/certbot renew --quiet
```

## 📦 Déploiement de l'Application

### 1. Préparation du Projet

```bash
# Création répertoire
sudo mkdir -p /var/www/tourshop
sudo chown $USER:$USER /var/www/tourshop

# Clone du projet
cd /var/www/tourshop
git clone https://github.com/votre-organisation/tourshop-backend.git .

# Installation dépendances
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

### 2. Configuration de l'Environnement

```bash
# Copie configuration
cp .env.example .env

# Génération clé
php artisan key:generate

# Configuration .env
nano .env
```

```env
# Environnement
APP_NAME=TourShop
APP_ENV=production
APP_KEY=base64:votre_clé_générée
APP_DEBUG=false
APP_URL=https://votre-domaine.com

# Base de données
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tourshop
DB_USERNAME=tourshop_user
DB_PASSWORD=votre_mot_de_passe_complexe

# Cache
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.votre-fournisseur.com
MAIL_PORT=587
MAIL_USERNAME=votre_email@domaine.com
MAIL_PASSWORD=votre_mot_de_passe_mail
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tourshop.com
MAIL_FROM_NAME="${APP_NAME}"

# Services externes
GOOGLE_MAPS_API_KEY=votre_clé_google_maps
STRIPE_KEY=votre_clé_stripe_publique
STRIPE_SECRET=votre_clé_stripe_secrète
TWILIO_SID=votre_sid_twilio
TWILIO_TOKEN=votre_token_twilio

# Sécurité
SANCTUM_STATEFUL_DOMAINS=votre-domaine.com
SESSION_DOMAIN=.votre-domaine.com
```

### 3. Optimisation et Sécurité

```bash
# Permissions
sudo chown -R www-data:www-data /var/www/tourshop
sudo find /var/www/tourshop -type f -exec chmod 644 {} \;
sudo find /var/www/tourshop -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/tourshop/storage
sudo chmod -R 775 /var/www/tourshop/bootstrap/cache

# Optimisation Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Migration base de données
php artisan migrate --force

# Seed (si nécessaire)
php artisan db:seed --force
```

## ⚙️ Configuration des Services

### 1. Configuration PHP-FPM

```ini
# /etc/php/8.1/fpm/pool.d/www.conf
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.1-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

# Optimisations PHP
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 50M
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 128
php_admin_value[opcache.max_accelerated_files] = 4000
```

### 2. Configuration Redis

```conf
# /etc/redis/redis.conf
bind 127.0.0.1
port 6379
timeout 0
tcp-keepalive 300

# Sécurité
requirepass votre_redis_password
protected-mode yes

# Performance
maxmemory 256mb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000
```

### 3. Configuration Supervisord (Queue Workers)

```ini
# /etc/supervisor/conf.d/tourshop-worker.conf
[program:tourshop-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/tourshop/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/tourshop-worker.log
stopwaitsecs=3600
```

```bash
# Activation Supervisord
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start tourshop-worker:*
```

## 🔄 Processus de Déploiement Automatisé

### 1. Script de Déploiement (deploy.sh)

```bash
#!/bin/bash

# Variables
PROJECT_DIR="/var/www/tourshop"
BACKUP_DIR="/var/backups/tourshop"
LOG_FILE="/var/log/deploy.log"

# Logging
exec > >(tee -a $LOG_FILE)
exec 2>&1

echo "=== Déploiement TourShop - $(date) ==="

# Backup avant déploiement
echo "Création backup..."
sudo mkdir -p $BACKUP_DIR
sudo mysqldump tourshop > $BACKUP_DIR/db_backup_$(date +%Y%m%d_%H%M%S).sql
sudo tar -czf $BACKUP_DIR/files_backup_$(date +%Y%m%d_%H%M%S).tar.gz -C $PROJECT_DIR .

# Mise à jour code
echo "Mise à jour du code..."
cd $PROJECT_DIR
sudo -u www-data git pull origin main

# Installation dépendances
echo "Installation dépendances..."
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm install
sudo -u www-data npm run build

# Optimisation Laravel
echo "Optimisation Laravel..."
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Migration base de données
echo "Migration base de données..."
sudo -u www-data php artisan migrate --force

# Redémarrage services
echo "Redémarrage services..."
sudo systemctl reload php8.1-fpm
sudo systemctl reload nginx
sudo supervisorctl restart tourshop-worker:*

# Nettoyage cache
echo "Nettoyage cache..."
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear

echo "=== Déploiement terminé avec succès ==="
```

### 2. GitHub Actions (CI/CD)

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, xml, curl, zip, gd, pgsql, bcmath, intl
        
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
      
    - name: Run tests
      run: php artisan test
      
    - name: Deploy to server
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.SSH_KEY }}
        script: |
          cd /var/www/tourshop
          ./deploy.sh
```

## 📊 Monitoring et Maintenance

### 1. Configuration Logs

```bash
# Rotation logs
sudo nano /etc/logrotate.d/tourshop
```

```
/var/www/tourshop/storage/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload php8.1-fpm
    endscript
}
```

### 2. Monitoring avec Laravel Telescope

```bash
# Installation Telescope
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

### 3. Health Check Endpoint

```php
// routes/api.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version'),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'cache' => Cache::get('health_check') ? 'connected' : 'disconnected',
        'queue' => Queue::size() > 0 ? 'processing' : 'idle'
    ]);
});
```

## 🔒 Sécurité Avancée

### 1. Firewall avec UFW

```bash
# Configuration firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

### 2. Fail2ban

```bash
# Installation
sudo apt install fail2ban -y

# Configuration
sudo nano /etc/fail2ban/jail.local
```

```ini
[nginx-http-auth]
enabled = true
filter = nginx-http-auth
logpath = /var/log/nginx/error.log
maxretry = 3

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
logpath = /var/log/nginx/error.log
maxretry = 10
```

### 3. Hardening PHP

```ini
# /etc/php/8.1/fpm/php.ini
disable_functions = exec,passthru,shell_exec,system,proc_open,popen
allow_url_fopen = Off
allow_url_include = Off
expose_php = Off
display_errors = Off
log_errors = On
```

## 🚨 Gestion des Incidents

### 1. Script de Rollback

```bash
#!/bin/bash
# rollback.sh

BACKUP_DIR="/var/backups/tourshop"
LATEST_DB=$(ls -t $BACKUP_DIR/db_backup_*.sql | head -1)
LATEST_FILES=$(ls -t $BACKUP_DIR/files_backup_*.tar.gz | head -1)

echo "Rollback base de données..."
psql tourshop < $LATEST_DB

echo "Rollback fichiers..."
cd /var/www/tourshop
sudo rm -rf *
sudo tar -xzf $LATEST_FILES -C .

echo "Redémarrage services..."
sudo systemctl reload php8.1-fpm nginx
sudo supervisorctl restart tourshop-worker:*

echo "Rollback complété"
```

### 2. Monitoring Continu

```bash
# Script monitoring
#!/bin/bash
# monitor.sh

# Vérifier si le site répond
if ! curl -f -s https://votre-domaine.com/api/health > /dev/null; then
    echo "$(date): Site down - Restarting services" >> /var/log/monitor.log
    sudo systemctl restart php8.1-fpm nginx
fi

# Vérifier queue worker
if ! supervisorctl status tourshop-worker:00 | grep RUNNING; then
    echo "$(date): Queue worker down - Restarting" >> /var/log/monitor.log
    sudo supervisorctl restart tourshop-worker:*
fi
```

## 📈 Performance et Scalabilité

### 1. Configuration OPcache

```ini
; /etc/php/8.1/mods-available/opcache.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.load_comments=1
```

### 2. Configuration Nginx Cache

```nginx
# Cache API responses
location ~ ^/api/ {
    expires 5m;
    add_header Cache-Control "public, no-transform";
    
    try_files $uri $uri/ /index.php?$query_string;
}

# Cache static assets
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header Vary Accept-Encoding;
}
```

### 3. Load Balancing (Multi-servers)

```nginx
upstream tourshop_backend {
    least_conn;
    server 10.0.1.10:8000 weight=5 max_fails=3 fail_timeout=30s;
    server 10.0.1.11:8000 weight=5 max_fails=3 fail_timeout=30s;
    server 10.0.1.12:8000 weight=5 max_fails=3 fail_timeout=30s;
}

server {
    listen 443 ssl http2;
    server_name votre-domaine.com;
    
    location / {
        proxy_pass http://tourshop_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## ✅ Checklist de Déploiement

### Pré-déploiement
- [ ] Backup base de données et fichiers
- [ ] Validation environnement de staging
- [ ] Tests complets passés
- [ ] Review du code par l'équipe
- [ ] Documentation mise à jour

### Déploiement
- [ ] Code mis à jour sur serveur
- [ ] Dépendances installées
- [ ] Migration base de données
- [ ] Cache vidé et régénéré
- [ ] Services redémarrés

### Post-déploiement
- [ ] Tests smoke sur production
- [ ] Monitoring activé
- [ ] Logs vérifiés
- [ ] Performance testée
- [ ] Notification équipe envoyée

---

*Ce guide couvre un déploiement production-ready sécurisé et performant pour TourShop. Adaptez selon votre infrastructure et vos besoins spécifiques.*
