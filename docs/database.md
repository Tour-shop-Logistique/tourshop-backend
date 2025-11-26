# Database Schema - TourShop Backend

## 📋 Vue d'ensemble

Le schéma de base de données TourShop est conçu pour gérer un écosystème logistique international complet, avec une architecture normalisée et optimisée pour les performances.

## 🏗️ Architecture Générale

### Principes de Conception
- **UUID Primary Keys** : Pour la sécurité et la scalabilité
- **Soft Deletes** : Préservation des données historiques
- **Timestamps** : Traçabilité complète des modifications
- **JSON Fields** : Flexibilité pour les données complexes
- **Index Stratégiques** : Optimisation des requêtes fréquentes

## 📊 Structure des Tables

### 🔐 Utilisateurs & Authentification

#### `users`
```sql
id (UUID, Primary Key)
name (string, 255)
email (string, 255, unique)
telephone (string, 20, unique)
password (string, 255)
type (enum: client, agence, livreur, backoffice, admin)
email_verified_at (timestamp)
remember_token (string, 100)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `agences`
```sql
id (UUID, Primary Key)
user_id (UUID, Foreign Key → users.id)
nom (string, 255)
adresse (text)
telephone (string, 20)
email (string, 255)
pays (string, 100)
ville (string, 100)
code_postal (string, 20)
latitude (decimal, 10, 8)
longitude (decimal, 11, 8)
actif (boolean, default: true)
backoffice_id (UUID, Foreign Key → backoffices.id, nullable)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `backoffices`
```sql
id (UUID, Primary Key)
user_id (UUID, Foreign Key → users.id)
nom (string, 255)
adresse (text)
telephone (string, 20)
email (string, 255)
pays (string, 100)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `clients`
```sql
id (UUID, Primary Key)
user_id (UUID, Foreign Key → users.id)
nom (string, 255)
prenom (string, 255)
adresse (text)
telephone (string, 20)
email (string, 255)
pays (string, 100)
ville (string, 100)
code_postal (string, 20)
type_client (enum: particulier, entreprise)
entreprise_nom (string, 255, nullable)
entreprise_siret (string, 50, nullable)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `livreurs`
```sql
id (UUID, Primary Key)
user_id (UUID, Foreign Key → users.id)
nom (string, 255)
prenom (string, 255)
telephone (string, 20)
email (string, 255)
pays (string, 100)
ville (string, 100)
permis_conduire (string, 100)
vehicule_type (enum: voiture, moto, camionette, camion)
vehicule_immatriculation (string, 50)
agence_id (UUID, Foreign Key → agences.id)
actif (boolean, default: true)
disponible (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

### 🌍 Géographie & Zones

#### `zones`
```sql
id (UUID, Primary Key)
nom (string, 255)
code (string, 10, unique)
pays (string, 100)
type (enum: pays, region, ville, zone_specifique)
parent_id (UUID, Foreign Key → zones.id, nullable)
niveau (integer, default: 1)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

### 📦 Expéditions & Articles

#### `expeditions`
```sql
id (UUID, Primary Key)
reference (string, 50, unique)
code_suivi (string, 20, unique)
code_validation_reception (string, 6, nullable)

-- Relations
client_id (UUID, Foreign Key → clients.id)
agence_id (UUID, Foreign Key → agences.id)
destinataire_id (UUID, Foreign Key → destinataires.id, nullable)
livreur_enlevement_id (UUID, Foreign Key → livreurs.id, nullable)
livreur_livraison_id (UUID, Foreign Key → livreurs.id, nullable)

-- Zones
zone_depart_id (UUID, Foreign Key → zones.id)
zone_destination_id (UUID, Foreign Key → zones.id)

-- Mode et type
mode_expedition (enum: simple, groupage)
type_colis (string, 100, nullable)

-- Articles et dimensions
articles (json, nullable) -- [{longueur, largeur, hauteur, volume}]
photos_articles (json, nullable) -- [urls]
poids_total (decimal, 8, 2)
volume_total (decimal, 8, 2)

-- Tarification
montant_base (decimal, 10, 2)
montant_prestation (decimal, 10, 2)
montant_expedition (decimal, 10, 2)

-- Options domicile
is_enlevement_domicile (boolean, default: false)
coord_enlevement (json, nullable) -- {lat, lng, adresse}
instructions_enlevement (text, nullable)
distance_domicile_agence (decimal, 5, 2) -- km
frais_enlevement_domicile (decimal, 10, 2)

is_livraison_domicile (boolean, default: false)
coord_livraison (json, nullable) -- {lat, lng, adresse}
instructions_livraison (text, nullable)
frais_livraison_domicile (decimal, 10, 2)

-- Frais additionnels
frais_emballage (decimal, 10, 2, default: 0)
delai_retrait (integer, nullable) -- jours
is_retard_retrait (boolean, default: false)
frais_retard_retrait (decimal, 10, 2, default: 0)

-- Montants finaux
montant_total_expedition (decimal, 10, 2)
is_paiement_credit (boolean, default: false)

-- Statuts
statut_expedition (enum: en_attente, accepted, refused, cancelled, 
                    en_cours_enlevement, recu_agencia, en_transit_entrepot,
                    expedition_depart, expedition_arrivee, recu_agencia_destination,
                    en_attente_retrait, en_livraison, livre)
statut_paiement (enum: en_attente, paye, partiellement_paye, rembourse)

-- Dates importantes
date_prevue_enlevement (timestamp, nullable)
date_enlevement_reelle (timestamp, nullable)
date_livraison_agence (timestamp, nullable)
date_deplacement_entrepot (timestamp, nullable)
date_expedition_depart (timestamp, nullable)
date_expedition_arrivee (timestamp, nullable)
date_reception_agence (timestamp, nullable)
date_retrait_colis (timestamp, nullable)
date_reception_client (timestamp, nullable)

-- Tracking
code_suivi_expedition (string, 20, unique)
photo_livraison (string, 255, nullable)
signature_destinataire (text, nullable)
commission_livreur (decimal, 10, 2, default: 0)
commission_agence (decimal, 10, 2, default: 0)

-- Métadonnées
description (text, nullable)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `expedition_articles`
```sql
id (UUID, Primary Key)
expedition_id (UUID, Foreign Key → expeditions.id)
produit_id (UUID, Foreign Key → produits.id, nullable)
designation (string, 255)
reference (string, 100, nullable)
description (text, nullable)

-- Dimensions
poids (decimal, 8, 2)
longueur (decimal, 8, 2, nullable)
largeur (decimal, 8, 2, nullable)
hauteur (decimal, 8, 2, nullable)
volume (decimal, 8, 2, nullable)

-- Quantité et valeur
quantite (integer, default: 1)
valeur_declaree (decimal, 10, 2, nullable)

-- Métadonnées
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `destinataires`
```sql
id (UUID, Primary Key)
nom (string, 255)
prenom (string, 255)
telephone (string, 20)
email (string, 255, nullable)
adresse (text)
pays (string, 100)
ville (string, 100)
code_postal (string, 20)
piece_identite (string, 100, nullable)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

### 💰 Tarification

#### `tarifs_simple`
```sql
id (UUID, Primary Key)
indice_tranche (decimal, 8, 2)
mode_expedition (enum: simple)
zone_destination_id (UUID, Foreign Key → zones.id)
montant_base (decimal, 10, 2)
pourcentage_prestation_base (decimal, 5, 2)
montant_prestation_base (decimal, 10, 2)
montant_expedition_base (decimal, 10, 2)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `tarifs_groupage`
```sql
id (UUID, Primary Key)
indice_tranche (decimal, 8, 2)
mode_expedition (enum: groupage)
type_colis (string, 100)
zone_destination_id (UUID, Foreign Key → zones.id)
montant_base (decimal, 10, 2)
pourcentage_prestation_base (decimal, 5, 2)
montant_prestation_base (decimal, 10, 2)
montant_expedition_base (decimal, 10, 2)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `tarifs_agence_simple`
```sql
id (UUID, Primary Key)
agence_id (UUID, Foreign Key → agences.id)
tarif_simple_id (UUID, Foreign Key → tarifs_simple.id)
prix_zones (json) -- [{zone_destination_id, montant_base, pourcentage_prestation_agence, montant_prestation_agence, montant_expedition_agence}]
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `tarifs_agence_groupage`
```sql
id (UUID, Primary Key)
agence_id (UUID, Foreign Key → agences.id)
tarif_groupage_id (UUID, Foreign Key → tarifs_groupage.id)
prix_zones (json) -- [{zone_destination_id, montant_base, pourcentage_prestation_agence, montant_prestation_agence, montant_expedition_agence}]
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

### 📦 Produits & Catégories

#### `category_products`
```sql
id (UUID, Primary Key)
nom (string, 255)
description (text)
icone (string, 255, nullable)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `produits`
```sql
id (UUID, Primary Key)
category_id (UUID, Foreign Key → category_products.id)
nom (string, 255)
reference (string, 100, unique)
description (text)
poids_standard (decimal, 8, 2)
dimensions_standard (json, nullable) -- {longueur, largeur, hauteur}
valeur_standard (decimal, 10, 2)
photo (string, 255, nullable)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

### 🏢 Configuration Système

#### `grilles_tarifaires`
```sql
id (UUID, Primary Key)
type (enum: enlevement_domicile, livraison_domicile)
zone_depart_id (UUID, Foreign Key → zones.id)
distance_min (decimal, 5, 2)
distance_max (decimal, 5, 2)
montant (decimal, 10, 2)
devise (string, 3, default: XOF)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `frais_emballage`
```sql
id (UUID, Primary Key)
type_emballage (enum: standard, fragile, surdimensionne, special)
montant (decimal, 10, 2)
description (text, nullable)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
deleted_at (timestamp, nullable)
```

#### `configurations`
```sql
id (UUID, Primary Key)
cle (string, 100, unique)
valeur (text)
type_valeur (enum: string, number, boolean, json)
description (text)
created_at (timestamp)
updated_at (timestamp)
```

## 🔗 Relations Principales

### Diagramme de Relations Simplifié

```
users (1:1) → clients
users (1:1) → agences
users (1:1) → livreurs
users (1:1) → backoffices

clients (1:N) → expeditions
agences (1:N) → expeditions
livreurs (1:N) → expeditions (enlevement/livraison)
destinataires (1:N) → expeditions

zones (1:N) → expeditions (depart/destination)
zones (1:N) → tarifs_*
zones (1:N) → grilles_tarifaires

expeditions (1:N) → expedition_articles
expedition_articles (N:1) → produits

category_products (1:N) → produits

tarifs_simple (1:N) → tarifs_agence_simple
tarifs_groupage (1:N) → tarifs_agence_groupage
```

## 📈 Index Stratégiques

### Index de Performance
```sql
-- Expéditions
CREATE INDEX idx_expeditions_client_statut ON expeditions(client_id, statut_expedition);
CREATE INDEX idx_expeditions_agence_statut ON expeditions(agence_id, statut_expedition);
CREATE INDEX idx_expeditions_livreur ON expeditions(livreur_livraison_id, statut_expedition);
CREATE INDEX idx_expeditions_zones ON expeditions(zone_destination_id, mode_expedition);
CREATE INDEX idx_expeditions_dates ON expeditions(created_at, date_reception_client);
CREATE INDEX idx_expeditions_tracking ON expeditions(code_suivi_expedition);

-- Tarifs
CREATE INDEX idx_tarifs_simple_zone ON tarifs_simple(zone_destination_id, mode_expedition, indice_tranche);
CREATE INDEX idx_tarifs_groupage_zone ON tarifs_groupage(zone_destination_id, mode_expedition, type_colis, indice_tranche);
CREATE INDEX idx_tarifs_agence ON tarifs_agence_simple(agence_id, tarif_simple_id);

-- Articles
CREATE INDEX idx_articles_expedition ON expedition_articles(expedition_id);
CREATE INDEX idx_articles_produit ON expedition_articles(produit_id);

-- Zones
CREATE INDEX idx_zones_pays ON zones(pays, type);
CREATE INDEX idx_zones_parent ON zones(parent_id);
```

## 🔒 Contraintes et Validation

### Contraintes de Clés Étrangères
```sql
-- ON DELETE RESTRICT pour préserver l'intégrité
-- ON UPDATE CASCADE pour les changements d'ID

-- Soft deletes activés sur toutes les tables principales
-- UUIDs pour éviter les collisions
```

### Validation des Données
```sql
-- Enums pour les statuts et types
-- Check constraints pour les montants positifs
-- Unique constraints pour les références
```

## 📊 Statistiques et Reporting

### Vues Matérialisées
```sql
-- Statistiques des expéditions par agence
CREATE MATERIALIZED VIEW stats_expeditions_agences AS
SELECT 
    a.id as agence_id,
    a.nom as agence_nom,
    COUNT(e.id) as total_expeditions,
    SUM(e.montant_total_expedition) as total_ca,
    AVG(e.montant_total_expedition) as panier_moyen,
    COUNT(CASE WHEN e.statut_expedition = 'livre' THEN 1 END) as expeditions_livrees
FROM agences a
LEFT JOIN expeditions e ON a.id = e.agence_id
WHERE e.deleted_at IS NULL
GROUP BY a.id, a.nom;

-- Performance des livreurs
CREATE MATERIALIZED VIEW stats_livreurs AS
SELECT 
    l.id as livreur_id,
    l.nom || ' ' || l.prenom as livreur_nom,
    COUNT(e.id) as total_livraisons,
    AVG(EXTRACT(EPOCH FROM (e.date_reception_client - e.date_enlevement_reelle))/3600) as temps_moyen_livraison
FROM livreurs l
LEFT JOIN expeditions e ON l.id = e.livreur_livraison_id
WHERE e.statut_expedition = 'livre' AND e.deleted_at IS NULL
GROUP BY l.id, l.nom, l.prenom;
```

## 🔄 Migrations et Évolutions

### Versions du Schéma
- **v1.0** : Structure de base (users, agences, expeditions)
- **v1.1** : Ajout tarification dynamique
- **v1.2** : Système de validation par code
- **v1.3** : Options domicile et gestion retards
- **v2.0** : Architecture internationale et entrepôts

### Scripts de Migration
```bash
# Migration vers nouvelle version
php artisan migrate --step

# Rollback si nécessaire
php artisan migrate:rollback --step

# Fresh migration (développement)
php artisan migrate:fresh --seed
```

## 🛠️ Maintenance

### Tâches Automatiques
```bash
# Nettoyage des données expirées
php artisan model:prune

# Optimisation des index
php artisan db:optimize

# Backup automatique
php artisan db:backup
```

### Monitoring
- **Taille des tables** : Surveillance de la croissance
- **Performance des requêtes** : Identification des lenteurs
- **Intégrité des données** : Vérification des contraintes
- **Usage des index** : Analyse des plans d'exécution

---

*Ce schéma est conçu pour évoluer avec TourShop et supporter sa croissance internationale tout en maintenant des performances optimales.*
