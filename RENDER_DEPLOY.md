# Déploiement sur Render.com (Docker)

Ce projet est configuré pour être déployé sur **Render.com** en tant que **Web Service**.

## 1. Création du Web Service
- Connectez-vous à votre tableau de bord Render.
- Cliquez sur **New +** -> **Web Service**.
- Connectez votre dépôt GitHub/GitLab.
- Choisissez **Docker** comme runtime.

## 2. Variables d'Environnement (Advanced)
Dans la section "Environment", ajoutez les variables suivantes :

| Clé | Valeur | Note |
|-----|--------|------|
| `APP_KEY` | `base64:xxx...` | Résultat de `php artisan key:generate --show` |
| `APP_ENV` | `production` | Active le forçage HTTPS |
| `PORT` | `10000` | Port sur lequel Render écoute (par défaut 10000) |
| `DB_CONNECTION` | `pgsql` | |
| `DATABASE_URL` | `postgres://user:pass@host:port/dbname` | L'URL de votre base de données Render |

## 3. Base de données
- Il est recommandé de créer une base de données **PostgreSQL** directement sur Render.
- Copiez son **Internal Database URL** et collez-la dans la variable `DATABASE_URL` de votre Web Service.

## 4. Ce qui est géré automatiquement
- **SSL/HTTPS** : Render gère les certificats et termine le SSL. Laravel est configuré pour forcer le HTTPS en production via `AppServiceProvider`.
- **Port** : Le Dockerfile est configuré pour écouter dynamiquement sur le port défini par la variable `PORT`.
- **Cache** : Le script d'entrée (`docker/entrypoint.sh`) vide et recrée le cache Laravel à chaque démarrage.
