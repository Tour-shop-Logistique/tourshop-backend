# Workflow d'Enregistrement des Tarifs - TourShop

## 📋 Vue d'ensemble

Ce document décrit le processus complet de gestion tarifaire dans TourShop, de la configuration des tarifs de base par le backoffice à la personnalisation par les agences partenaires.

## 🏗️ Architecture Tarifaire

### Structure Hiérarchique

```
Tarifs de Base (Backoffice)
├── Tarifs Livraison Domicile (LD)
│   └── Par zone + indice + montant_base
├── Tarifs Groupage
│   ├── Groupage Afrique (Par pays de destination + prix unitaire)
│   ├── Groupage CA (Prix unitaire global)
│   └── Groupage PA (Basé sur prix_kg de la catégorie produit + mode de transport)
└── Grilles Tarifaires Additionnelles
    ├── Enlèvement domicile
    ├── Livraison domicile
    └── Frais emballage

Tarifs d'Agence (Personnalisés)
├── Tarifs LD: Pourcentages personnalisés par zone
├── Tarifs Groupage: Activation des tarifs backoffice + personnalisation modes
├── Basés sur tarifs de base
└── Calculs automatiques des montants finaux
```

## 🔄 Workflow Complet

### Phase 1: Configuration des Tarifs de Base (Backoffice)

#### 1.1 Gestion des Zones Géographiques

```php
// Backoffice\ZoneController
POST /api/backoffice/zones
```

**Processus:**
1. **Création des zones hiérarchiques**
   - Pays → Régions → Villes → Zones spécifiques
   - Configuration des relations parent-enfant
   - Définition des niveaux (1: pays, 2: région, etc.)

**Données requises:**
```json
{
    "nom": "France",
    "code": "FR",
    "pays": "France",
    "type": "pays",
    "parent_id": null,
    "niveau": 1,
    "actif": true
}
```

#### 1.2 Configuration des Tarifs Livraison Domicile (LD)

```php
// Backoffice\TarifSimpleController
POST /api/backoffice/tarifs-simple
```

**Processus:**
1. **Définition des tranches d'indice** (poids/volume)
2. **Configuration par zone de destination**
3. **Calcul automatique des montants de prestation**
4. **Validation des prix plancher**

**Données requises:**
```json
{
    "indice": 1.5,
    "type_expedition": "simple",
    "pays": "États-Unis",
    "prix_zones": [
        {
            "zone_destination_id": "uuid-zone-new-york",
            "montant_base": 15000,
            "pourcentage_prestation": 20
        },
        {
            "zone_destination_id": "uuid-zone-los-angeles", 
            "montant_base": 16000,
            "pourcentage_prestation": 22
        }
    ],
    "actif": true
}
```

#### 1.3 Configuration des Tarifs Groupage

```php
// Backoffice\TarifGroupageController  
POST /api/backoffice/tarifs-groupage
```

**Processus:**
1. **Définition du type d'expédition** (`groupage_afrique`, `groupage_ca`, `groupage_pa`)
2. **Définition du prix unitaire** (pour Afrique et CA)
3. **Catégorie de produits** (Optionnelle selon le type, obligatoire pour PA)
4. **Pays** (Obligatoire pour Afrique)
5. **Modes de transport multiples** (Optionnels)

**Données requises (Exemple Groupage Afrique):**
```json
{
    "category_id": "uuid-category-electronique",
    "type_expedition": "groupage_afrique",
    "prix_unitaire": 13500,
    "pays": "Sénégal",
    "prix_modes": [
        {
            "mode": "avion",
            "montant_base": 25000,
            "pourcentage_prestation": 25
        },
        {
            "mode": "bateau", 
            "montant_base": 18000,
            "pourcentage_prestation": 20
        },
        {
            "mode": "accompagne",
            "montant_base": 35000,
            "pourcentage_prestation": 30
        }
    ],
    "actif": true
}
```

**Données requises (Exemple Groupage CA):**
```json
{
    "type_expedition": "groupage_ca",
    "prix_unitaire": 15000,
    "actif": true
}
```

**Données requises (Exemple Groupage PA):**
```json
{
    "category_id": "uuid-category-electronique",
    "type_expedition": "groupage_pa",
    "pays": "France",
    "prix_modes": [
        {
            "mode": "avion",
            "montant_base": 30000,
            "pourcentage_prestation": 28
        }
    ],
    "actif": true
}
```

**Note importante:** Pour **Groupage PA** (Côte d'Ivoire ↔ France), le prix unitaire utilisé lors du calcul d'expédition est le `prix_kg` de la **catégorie de produit** associée au premier article du colis, et non un `prix_unitaire` défini dans le tarif groupage.

### Phase 2: Personnalisation par les Agences

#### 2.1 Consultation des Tarifs de Base Disponibles

```php
// Agence\AgenceTarifGroupageController
GET /api/agence/tarifs-groupage/list
```

**Processus:**
1. **Récupération des tarifs disponibles**
2. **Inclusion des détails (prix unitaire, type, pays)**

**Réponse:**
```json
{
    "success": true,
    "tarifs": [
        {
            "id": "uuid-tarif-agence",
            "tarifGroupage": {
                "id": "uuid-tarif-base",
                "type_expedition": "groupage_afrique",
                "prix_unitaire": 13500,
                "pays": "Sénégal"
            },
            "category": { ... }
        }
    ]
}
```

#### 2.2 Création des Tarifs Personnalisés

##### Tarif LD Personnalisé (Anciennement Simple)
```php
// Agence\TarifController
POST /api/agence/add-tarif-simple
```

**Données requises:**
```json
{
    "tarif_simple_id": "uuid-tarif-base",
    "prix_zones": [
        {
            "zone_destination_id": "uuid-zone-usa",
            "pourcentage_prestation": 25
        }
    ]
}
```

##### Tarif Groupage Personnalisé
```php
// Agence\AgenceTarifGroupageController
POST /api/agence/tarifs-groupage/add
```

**Processus:**
1. **Lier un tarif groupage backoffice à l'agence**
2. **Personnaliser les prix des modes (optionnel)**

```json
{
    "tarif_groupage_id": "uuid-tarif-groupage-base",
    "category_id": "uuid-category-electronique",
    "prix_modes": [
        {
            "mode": "avion",
            "pourcentage_prestation": 30
        },
        {
            "mode": "bateau",
            "pourcentage_prestation": 25
        },
        {
            "mode": "accompagne",
            "pourcentage_prestation": 35
        }
    ]
}
```

### Phase 3: Utilisation des Tarifs (Création Expédition)

#### 3.1 Logique de Tarification (Backend)

**Algorithme de calcul du prix unitaire (AgenceExpeditionController):**

1. **Livraison Domicile (LD)**:
   - Utilise les grilles de tarifs simples basées sur l'indice et la zone.

2. **Groupage PA (Côte d'Ivoire ↔ France)**:
   - Utilise le `prix_kg` défini dans la **catégorie du produit** du premier article.
   - **Important:** Le prix_kg provient de la table `category_products`, pas du tarif groupage.

3. **Groupage Afrique**:
   - Recherche le `TarifAgenceGroupage` lié à l'agence.
   - Filtre par `type_expedition = groupage_afrique`.
   - Filtre par `pays` de destination (correspondance exacte).
   - Utilise le `prix_unitaire` du tarif backoffice associé.

4. **Groupage CA**:
   - Recherche le `TarifAgenceGroupage` lié à l'agence.
   - Filtre par `type_expedition = groupage_ca`.
   - Utilise le `prix_unitaire` du tarif backoffice associé.

5. **Calcul final**:
   - `prix_total = prix_unitaire * poids_colis`

---

*Ce workflow garantit une gestion tarifaire robuste, flexible et adaptée aux différents types d'expédition (LD, Afrique, CA, PA).*
