# API Documentation - TourShop Backend

## 📋 Vue d'ensemble

L'API TourShop RESTful fournit un accès complet à toutes les fonctionnalités de la plateforme logistique internationale. Elle est conçue pour être sécurisée, performante et facile à intégrer.

## 🔐 Authentification

### Laravel Sanctum
TourShop utilise Laravel Sanctum pour l'authentification API avec tokens JWT.

#### Génération de Token
```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "client@tourshop.com",
    "password": "password123",
    "type": "client"
}
```

**Réponse:**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": "uuid",
            "name": "Client Name",
            "email": "client@tourshop.com",
            "type": "client"
        },
        "token": "1|abc123def456...",
        "expires_at": "2025-12-26T01:00:00Z"
    }
}
```

#### Utilisation du Token
```http
Authorization: Bearer 1|abc123def456...
```

#### Révocation de Token
```http
POST /api/auth/logout
Authorization: Bearer 1|abc123def456...
```

## 📊 Structure des Réponses

### Format Standard
```json
{
    "success": true|false,
    "message": "Message descriptif",
    "data": {}, // ou []
    "errors": {}, // en cas d'erreur de validation
    "meta": {
        "pagination": {
            "current_page": 1,
            "per_page": 15,
            "total": 100,
            "last_page": 7
        }
    }
}
```

### Codes HTTP
- `200` : Succès
- `201` : Créé
- `400` : Requête invalide
- `401` : Non authentifié
- `403` : Non autorisé
- `404` : Ressource non trouvée
- `422` : Erreur de validation
- `429` : Trop de requêtes
- `500` : Erreur serveur

## 👥 Endpoints par Rôle

### 🔵 Client API

#### Authentification
```http
POST /api/client/register
POST /api/client/login
POST /api/client/logout
POST /api/client/refresh-token
```

#### Expéditions
```http
GET    /api/client/expeditions              # Lister les expéditions
POST   /api/client/expeditions/initiate     # Créer une expédition
GET    /api/client/expeditions/{id}         # Détails d'une expédition
PUT    /api/client/expeditions/{id}/cancel  # Annuler une expédition
POST   /api/client/expeditions/simulate     # Simuler un tarif
GET    /api/client/expeditions/statistics   # Statistiques personnelles
```

#### Articles
```http
GET    /api/expeditions/{expeditionId}/articles       # Lister les articles
POST   /api/expeditions/{expeditionId}/articles       # Ajouter un article
PUT    /api/expeditions/{expeditionId}/articles/{id}  # Modifier un article
DELETE /api/expeditions/{expeditionId}/articles/{id}  # Supprimer un article
```

#### Destinataires
```http
GET    /api/client/destinataires          # Lister les destinataires
POST   /api/client/destinataires          # Ajouter un destinataire
GET    /api/client/destinataires/{id}     # Détails destinataire
PUT    /api/client/destinataires/{id}     # Modifier destinataire
DELETE /api/client/destinataires/{id}     # Supprimer destinataire
```

#### Tracking Public
```http
GET    /api/public/tracking/{code_suivi}   # Tracking sans authentification
```

### 🟢 Agence API

#### Authentification
```http
POST /api/agence/login
POST /api/agence/logout
```

#### Expéditions
```http
GET    /api/agence/expeditions              # Lister les expéditions
GET    /api/agence/expeditions/{id}         # Détails expédition
PUT    /api/agence/expeditions/{id}/validate # Valider expédition
PUT    /api/agence/expeditions/{id}/refuse   # Refuser expédition
POST   /api/agence/expeditions/{id}/ship    # Expédier vers entrepôt
```

#### Tarifs Simple
```http
GET    /api/agence/list-tarifs-simple       # Lister les tarifs simples
POST   /api/agence/add-tarif-simple         # Créer un tarif simple
PUT    /api/agence/edit-tarif-simple/{id}   # Modifier un tarif simple
GET    /api/agence/show-tarif-simple/{id}   # Détails tarif simple
DELETE /api/agence/delete-tarif-simple/{id} # Supprimer un tarif simple
PUT    /api/agence/status-tarif-simple/{id} # Activer/désactiver tarif
```

#### Tarifs Groupage
```http
GET    /api/agence/list-tarifs-groupage      # Lister les tarifs groupage
POST   /api/agence/add-tarif-groupage        # Créer un tarif groupage
PUT    /api/agence/edit-tarif-groupage/{id}  # Modifier un tarif groupage
GET    /api/agence/show-tarif-groupage/{id}  # Détails tarif groupage
DELETE /api/agence/delete-tarif-groupage/{id} # Supprimer un tarif groupage
PUT    /api/agence/status-tarif-groupage/{id} # Activer/désactiver tarif
```

#### Statistiques
```http
GET    /api/agence/statistics                # Statistiques agence
GET    /api/agence/performance              # Performance livreurs
```

### 🟡 Livreur API

#### Authentification
```http
POST /api/livreur/login
POST /api/livreur/logout
```

#### Missions
```http
GET    /api/livreur/expeditions             # Missions du jour
GET    /api/livreur/expeditions/{id}        # Détails mission
```

#### Enlèvements
```http
POST   /api/livreur/enlevement/{id}/start   # Démarrer enlèvement
POST   /api/livreur/reception-agence/{id}/confirm # Confirmer réception agence
```

#### Livraisons
```http
POST   /api/livreur/livraison/{id}/start    # Démarrer livraison
POST   /api/livreur/livraison/{id}/validate  # Valider avec code
```

#### Position
```http
POST   /api/livreur/position                 # Mettre à jour position GPS
```

### 🔴 Backoffice API

#### Utilisateurs
```http
GET    /api/backoffice/users                 # Lister tous les utilisateurs
POST   /api/backoffice/users                 # Créer utilisateur
GET    /api/backoffice/users/{id}            # Détails utilisateur
PUT    /api/backoffice/users/{id}            # Modifier utilisateur
DELETE /api/backoffice/users/{id}            # Supprimer utilisateur
```

#### Agences
```http
GET    /api/backoffice/agences               # Lister les agences
POST   /api/backoffice/agences               # Créer agence
GET    /api/backoffice/agences/{id}          # Détails agence
PUT    /api/backoffice/agences/{id}          # Modifier agence
DELETE /api/backoffice/agences/{id}          # Supprimer agence
```

#### Zones
```http
GET    /api/backoffice/zones                 # Lister les zones
POST   /api/backoffice/zones                 # Créer zone
GET    /api/backoffice/zones/{id}            # Détails zone
PUT    /api/backoffice/zones/{id}            # Modifier zone
DELETE /api/backoffice/zones/{id}            # Supprimer zone
```

#### Tarifs Base
```http
GET    /api/backoffice/tarifs-simple          # Lister tarifs simples base
POST   /api/backoffice/tarifs-simple          # Créer tarif simple base
PUT    /api/backoffice/tarifs-simple/{id}     # Modifier tarif simple base
DELETE /api/backoffice/tarifs-simple/{id}     # Supprimer tarif simple base

GET    /api/backoffice/tarifs-groupage        # Lister tarifs groupage base
POST   /api/backoffice/tarifs-groupage        # Créer tarif groupage base
PUT    /api/backoffice/tarifs-groupage/{id}   # Modifier tarif groupage base
DELETE /api/backoffice/tarifs-groupage/{id}   # Supprimer tarif groupage base
```

#### Produits
```http
GET    /api/backoffice/produits               # Lister les produits
POST   /api/backoffice/produits               # Créer produit
GET    /api/backoffice/produits/{id}          # Détails produit
PUT    /api/backoffice/produits/{id}          # Modifier produit
DELETE /api/backoffice/produits/{id}          # Supprimer produit
```

#### Catégories
```http
GET    /api/backoffice/categories             # Lister les catégories
POST   /api/backoffice/categories             # Créer catégorie
GET    /api/backoffice/categories/{id}        # Détails catégorie
PUT    /api/backoffice/categories/{id}        # Modifier catégorie
DELETE /api/backoffice/categories/{id}        # Supprimer catégorie
```

#### Statistiques Globales
```http
GET    /api/backoffice/statistics             # Statistiques globales
GET    /api/backoffice/performance            # Performance système
GET    /api/backoffice/revenue                # Revenus et commissions
```

## 📝 Exemples d'Utilisation

### Création d'Expédition (Client)

```http
POST /api/client/expeditions/initiate
Authorization: Bearer 1|abc123def456...
Content-Type: application/json

{
    "agence_id": "uuid-agence",
    "zone_depart_id": "uuid-zone-depart",
    "zone_destination_id": "uuid-zone-destination",
    "mode_expedition": "simple",
    "type_colis": null,
    "is_enlevement_domicile": true,
    "coord_enlevement": {
        "lat": 48.8566,
        "lng": 2.3522,
        "adresse": "123 Rue de la Paix, Paris, France"
    },
    "is_livraison_domicile": true,
    "coord_livraison": {
        "lat": 40.7128,
        "lng": -74.0060,
        "adresse": "456 Broadway, New York, USA"
    },
    "description": "Colis contenant des vêtements"
}
```

**Réponse:**
```json
{
    "success": true,
    "message": "Expédition initiée avec succès. Code de validation envoyé.",
    "data": {
        "id": "uuid-expedition",
        "reference": "TS-2025-12345",
        "code_suivi": "ABC12345",
        "code_validation_reception": "X7Y9Z2",
        "statut_expedition": "en_attente",
        "montant_estime": 15000,
        "agence": {
            "id": "uuid-agence",
            "nom": "Agence Paris Centre"
        },
        "created_at": "2025-11-26T01:00:00Z"
    }
}
```

### Ajout d'Article

```http
POST /api/expeditions/{expeditionId}/articles
Authorization: Bearer 1|abc123def456...
Content-Type: application/json

{
    "designation": "Chemise en coton",
    "reference": "CHM-001",
    "poids": 0.5,
    "longueur": 30,
    "largeur": 25,
    "hauteur": 5,
    "quantite": 2,
    "valeur_declaree": 50000,
    "description": "Chemises de marque neuves"
}
```

### Simulation de Tarif

```http
POST /api/client/expeditions/simulate
Authorization: Bearer 1|abc123def456...
Content-Type: application/json

{
    "agence_id": "uuid-agence",
    "zone_depart_id": "uuid-zone-depart",
    "zone_destination_id": "uuid-zone-destination",
    "mode_expedition": "simple",
    "is_enlevement_domicile": true,
    "coord_enlevement": {
        "lat": 48.8566,
        "lng": 2.3522
    },
    "is_livraison_domicile": true,
    "coord_livraison": {
        "lat": 40.7128,
        "lng": -74.0060
    },
    "articles": [
        {
            "designation": "Chemise en coton",
            "poids": 0.5,
            "longueur": 30,
            "largeur": 25,
            "hauteur": 5,
            "quantite": 2,
            "valeur_declaree": 50000
        }
    ]
}
```

**Réponse:**
```json
{
    "success": true,
    "message": "Simulation de tarif réussie",
    "data": {
        "montant_base": 12000,
        "montant_prestation": 2400,
        "montant_expedition": 14400,
        "frais_enlevement_domicile": 1500,
        "frais_livraison_domicile": 2000,
        "frais_emballage": 500,
        "montant_total": 18400,
        "devise": "XOF",
        "details": {
            "distance_enlevement_km": 3.2,
            "distance_livraison_km": 5.8,
            "poids_total_kg": 1.0,
            "volume_total_cm3": 7500
        }
    }
}
```

### Validation Livraison (Livreur)

```http
POST /api/livreur/livraison/{expeditionId}/validate
Authorization: Bearer 1|livreur_token...
Content-Type: application/json

{
    "code_validation": "X7Y9Z2"
}
```

**Réponse:**
```json
{
    "success": true,
    "message": "Colis livré avec succès!",
    "data": {
        "id": "uuid-expedition",
        "statut_expedition": "livre",
        "date_reception_client": "2025-11-26T14:30:00Z",
        "code_validation_reception": null
    }
}
```

## 🔄 Webhooks

### Configuration
Les webhooks permettent de recevoir des notifications en temps réel sur votre serveur.

```http
POST /api/webhooks/configure
Authorization: Bearer 1|backoffice_token...
Content-Type: application/json

{
    "url": "https://votre-serveur.com/webhook/tourshop",
    "events": [
        "expedition.created",
        "expedition.status_changed",
        "expedition.delivered",
        "payment.completed"
    ],
    "secret": "votre_secret_webhook"
}
```

### Événements Disponibles
- `expedition.created` : Nouvelle expédition créée
- `expedition.status_changed` : Changement de statut
- `expedition.delivered` : Livraison effectuée
- `payment.completed` : Paiement validé
- `user.registered` : Nouvel utilisateur inscrit

### Format Webhook
```json
{
    "event": "expedition.status_changed",
    "data": {
        "expedition_id": "uuid",
        "old_status": "en_livraison",
        "new_status": "livre",
        "timestamp": "2025-11-26T14:30:00Z"
    },
    "signature": "sha256=abc123..."
}
```

## 📊 Rate Limiting

### Limites par Rôle
- **Client** : 1000 requêtes/heure
- **Agence** : 5000 requêtes/heure  
- **Livreur** : 2000 requêtes/heure
- **Backoffice** : 10000 requêtes/heure

### Headers Rate Limit
```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1668422400
```

## 🚨 Gestion des Erreurs

### Format Erreur
```json
{
    "success": false,
    "message": "Erreur de validation",
    "errors": {
        "email": ["L'email est requis"],
        "telephone": ["Le format du téléphone est invalide"]
    },
    "code": 422
}
```

### Codes d'Erreur Spécifiques
- `AUTH_001` : Token invalide
- `AUTH_002` : Token expiré
- `PERM_001` : Permissions insuffisantes
- `EXP_001` : Expédition non trouvée
- `EXP_002` : Expédition ne peut plus être modifiée
- `TARIF_001` : Tarif non disponible pour cette zone
- `CODE_001` : Code de validation incorrect

## 🧪 Testing

### Environnement de Test
```bash
# URL de base
https://api-staging.tourshop.com

# Utilisateur de test
Email: test@tourshop.com
Password: Test123456
```

### Postman Collection
Une collection Postman est disponible avec tous les endpoints exemples :
```bash
curl -O https://docs.tourshop.com/api/postman-collection.json
```

## 📈 Monitoring

### Health Check
```http
GET /api/health
```

**Réponse:**
```json
{
    "status": "ok",
    "timestamp": "2025-11-26T14:30:00Z",
    "version": "2.1.0",
    "database": "connected",
    "cache": "connected",
    "queue": "processing"
}
```

### Metrics
```http
GET /api/metrics
Authorization: Bearer 1|backoffice_token...
```

## 📚 SDKs

### JavaScript/Node.js
```bash
npm install @tourshop/api-client
```

```javascript
import { TourShopAPI } from '@tourshop/api-client';

const client = new TourShopAPI({
    baseURL: 'https://api.tourshop.com',
    token: 'votre_token'
});

const expedition = await client.expeditions.create({
    agence_id: 'uuid',
    zone_destination_id: 'uuid',
    mode_expedition: 'simple'
});
```

### PHP
```bash
composer require tourshop/php-sdk
```

```php
use TourShop\TourShopClient;

$client = new TourShopClient('votre_token');
$expedition = $client->expeditions()->create([
    'agence_id' => 'uuid',
    'zone_destination_id' => 'uuid',
    'mode_expedition' => 'simple'
]);
```

---

*Pour toute question sur l'API, contactez notre équipe technique à api@tourshop.com*
