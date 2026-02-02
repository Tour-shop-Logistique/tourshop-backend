# Database Schema - TourShop Backend

## 📋 Vue d'ensemble

Le schéma de base de données TourShop est conçu pour gérer un écosystème logistique international complet, avec une architecture normalisée et optimisée pour les performances.

## 🏗️ Architecture Générale

### Principes de Conception
- **UUID Primary Keys** : Utilisé pour la plupart des tables (Agences, Expeditions, Colis, etc.) pour la sécurité et la scalabilité.
- **Soft Deletes** : Préservation des données historiques (si configuré sur les modèles).
- **Timestamps** : Traçabilité complète des modifications (`created_at`, `updated_at`).
- **JSONB Fields** : Utilisé massivement pour les structures de données flexibles (expéditeur, destinataire, articles de colis, tarifs par zone).
- **Index Stratégiques** : Optimisation des requêtes fréquentes sur les statuts, les références et les clés étrangères.

## 📊 Structure des Tables

### 🔐 Utilisateurs & Authentification

#### `users`
```sql
id (UUID, Primary Key)
name (string, 255)
email (string, 255, unique)
telephone (string, 20, unique)
password (string, 255)
type (enum: client, agence, livreur, backoffice)
email_verified_at (timestamp, nullable)
remember_token (string, 100, nullable)
created_at (timestamp)
updated_at (timestamp)
```

#### `agences`
```sql
id (UUID, Primary Key)
user_id (UUID, Foreign Key → users.id)
code_agence (string, 20)
nom_agence (string)
description (text, nullable)
adresse (string)
ville (string)
commune (string, nullable)
pays (string)
telephone (string, 20, nullable)
latitude (decimal, 10, 8)
longitude (decimal, 11, 8)
horaires (jsonb, nullable)
photos (jsonb, nullable)
logo (string, nullable)
actif (boolean, default: true)
message_accueil (text, nullable)
created_at (timestamp)
updated_at (timestamp)
```

#### `backoffices`
```sql
id (UUID, Primary Key)
user_id (UUID, Foreign Key → users.id) -- Administrateur créateur
nom_organisation (string)
telephone (string)
localisation (string, nullable)
adresse (string)
ville (string)
commune (string, nullable)
pays (string)
email (string, nullable)
logo (string, nullable)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
```

#### `livreurs`
```sql
id (UUID, Primary Key)
user_id (UUID, Foreign Key → users.id)
agence_id (UUID, Foreign Key → agences.id)
permis_de_conduire (string, nullable)
type_vehicule (string, nullable) -- Ex: Moto, Voiture
numero_vehicule (string, nullable) -- Immatriculation
zone_de_livraison_km (decimal, 5, 2, nullable)
statut (enum: disponible, en_service, en_pause, hors_service)
created_at (timestamp)
updated_at (timestamp)
```

### 🌍 Géographie & Zones

#### `zones`
```sql
id (string, Primary Key) -- Souvent un slug ou code (ex: 'zone-1')
nom (string)
pays (jsonb) -- Tableau des noms de pays inclus
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
```

### 📦 Expéditions & Colis

#### `expeditions`
```sql
id (UUID, Primary Key)
reference (string, unique) -- Ex: EXP-20250101-1234
code_suivi_expedition (string, unique, nullable)
code_validation_reception (string, nullable) -- Code à 6 chiffres

-- Relations
user_id (UUID, Foreign Key → users.id) -- Propriétaire/Client
agence_id (UUID, Foreign Key → agences.id)

-- Livreurs pour les différentes étapes
livreur_enlevement_id (UUID, nullable, Foreign Key → livreurs.id)
livreur_deplacement_id (UUID, nullable, Foreign Key → livreurs.id)
livreur_livraison_id (UUID, nullable, Foreign Key → livreurs.id)

-- Contacts (Dénormalisés en JSON pour historique fidèle)
expediteur (jsonb) -- {nom_prenom, telephone, email, adresse, ville, etc.}
destinataire (jsonb) -- {nom_prenom, telephone, email, adresse, ville, etc.}

-- Localisation
zone_depart_id (string, nullable)
pays_depart (string, nullable)
zone_destination_id (string, nullable)
pays_destination (string, nullable)

-- Mode et Statuts
type_expedition (string) -- simple (LD), groupage_afrique, groupage_ca, groupage_dhd_aerien, etc.
statut_expedition (string, default: 'EN_ATTENTE')
statut_paiement (string, default: 'EN_ATTENTE')

-- Financier Principal
montant_base (decimal, 12, 2)
pourcentage_prestation (decimal, 5, 2, nullable)
montant_prestation (decimal, 12, 2)
montant_expedition (decimal, 12, 2)

-- Frais Additionnels
frais_enlevement_domicile (decimal, 12, 2)
frais_livraison_domicile (decimal, 12, 2)
frais_emballage (decimal, 12, 2)
frais_enlevement_agence (decimal, 12, 2)
frais_retard_retrait (decimal, 12, 2)
frais_douane (decimal, 12, 2)

-- Options de Service
is_enlevement_domicile (boolean, default: false)
coord_enlevement (string, nullable) -- Adresse/Coordonnées texte
instructions_enlevement (text, nullable)
distance_domicile_agence (decimal, 8, 2, nullable)

is_livraison_domicile (boolean, default: false)
coord_livraison (string, nullable)
instructions_livraison (text, nullable)

delai_retrait (string, nullable)
is_retard_retrait (boolean, default: false)
is_paiement_credit (boolean, default: false)

-- Commissions
commission_livreur_enlevement (decimal, 12, 2)
commission_agence_enlevement (decimal, 12, 2)
commission_livreur_livraison (decimal, 12, 2)
commission_agence_livraison (decimal, 12, 2)
commission_agence_retard (decimal, 12, 2)
commission_tourshop_retard (decimal, 12, 2)

-- Dates du Workflow
date_prevue_enlevement (timestamp, nullable)
date_enlevement_client (timestamp, nullable)
date_livraison_agence (timestamp, nullable)
date_deplacement_entrepot (timestamp, nullable)
date_expedition_depart (timestamp, nullable)
date_expedition_arrivee (timestamp, nullable)
date_reception_agence (timestamp, nullable)
date_limite_retrait (timestamp, nullable)
date_reception_client (timestamp, nullable)
date_livraison_reelle (timestamp, nullable)
date_annulation (timestamp, nullable)
motif_annulation (text, nullable)

created_at (timestamp)
updated_at (timestamp)
```

#### `colis`
```sql
id (UUID, Primary Key)
expedition_id (UUID, Foreign Key → expeditions.id)
category_id (UUID, nullable, Foreign Key → category_products.id)
code_colis (string)
designation (string, nullable)
articles (jsonb) -- Tableau d'articles: [{designation, poids, quantite, etc.}]
photo (string, nullable)
longueur (decimal, 10, 2) -- cm
largeur (decimal, 10, 2) -- cm
hauteur (decimal, 10, 2) -- cm
volume (decimal, 10, 2) -- cm³
poids (decimal, 10, 2) -- kg

-- Tarification individuelle du colis
prix_emballage (decimal, 10, 2)
prix_unitaire (decimal, 10, 2) -- prix au kg
montant_colis_base (decimal, 10, 2)
pourcentage_prestation (decimal, 10, 2)
montant_colis_prestation (decimal, 10, 2)
montant_colis_total (decimal, 10, 2)

created_at (timestamp)
updated_at (timestamp)
```

### 💰 Tarification Dynamique

#### `tarifs_simple` (Backoffice)
```sql
id (UUID, Primary Key)
backoffice_id (UUID, Foreign Key → backoffices.id)
type_expedition (string) -- simple (LD)
indice (decimal, 5, 1) -- Poids ou Volume pivot
zone_destination_id (string, Foreign Key → zones.id)
montant_base (decimal, 12, 2)
pourcentage_prestation (decimal, 5, 2)
montant_prestation (decimal, 12, 2)
montant_expedition (decimal, 12, 2)
pays (string, nullable)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
```

#### `tarifs_agence_simple`
```sql
id (UUID, Primary Key)
agence_id (UUID, Foreign Key → agences.id)
tarif_simple_id (UUID, Foreign Key → tarifs_simple.id)
indice (decimal, 5, 1) -- Recopie pour perf
zone_destination_id (string, Foreign Key → zones.id)
montant_base (decimal, 12, 2)
pourcentage_prestation (decimal, 5, 2)
montant_prestation (decimal, 12, 2)
montant_expedition (decimal, 12, 2)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
```

#### `tarifs_groupage` (Backoffice)
```sql
id (UUID, Primary Key)
category_id (UUID, Foreign Key → category_products.id)
type_expedition (string) -- groupage_afrique, groupage_ca, dhd_aerien, etc.
mode (string, nullable) -- avion, bateau, etc.
ligne (string, nullable) -- ex: "paris-abidjan"
montant_base (decimal, 10, 2)
pourcentage_prestation (decimal, 10, 2)
montant_prestation (decimal, 10, 2)
montant_expedition (decimal, 10, 2)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
```

#### `tarifs_agence_groupage`
```sql
id (UUID, Primary Key)
agence_id (UUID, Foreign Key → agences.id)
tarif_groupage_id (UUID, Foreign Key → tarifs_groupage.id)
category_id (UUID, Foreign Key → category_products.id)
type_expedition (string)
mode (string, nullable)
ligne (string, nullable)
montant_base (decimal, 10, 2)
pourcentage_prestation (decimal, 10, 2)
montant_prestation (decimal, 10, 2)
montant_expedition (decimal, 10, 2)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
```

### 📦 Produits & Catégories

#### `category_products`
```sql
id (UUID, Primary Key)
nom (string, 150)
backoffice_id (UUID, Foreign Key → backoffices.id)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
```

#### `produits`
```sql
id (UUID, Primary Key)
category_id (UUID, Foreign Key → category_products.id)
nom (string, 255)
reference (string, 100, unique)
description (text, nullable)
poids_standard (decimal, 10, 2)
dimensions_standard (json, nullable) -- {L, l, H}
valeur_standard (decimal, 10, 2)
photo (string, nullable)
actif (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)
```

## 🔗 Relations Principales

- **users** possède son profil via **agences**, **livreurs** ou **backoffices**.
- **expeditions** est le cœur du système, lié à une agence et un utilisateur, et contient plusieurs **colis**.
- **colis** est lié à une **category_products** pour déterminer son tarif groupage (DHD).
- **tarifs_agence_*** sont des extensions personnalisées des **tarifs_*** définis globalement par le backoffice.

## 🔒 Contraintes et Validation

- **UUIDs** systématiques pour l'intégrité globale.
- **Indexes** sur `reference`, `code_suivi`, `statuts` et clés étrangères pour la performance.
- **Foreign Keys** avec `onDelete('cascade')` ou `nullOnDelete()` selon le besoin métier.

---

*Ce document décrit l'état actuel de la base de données TourShop. Pour toute modification de schéma, veuillez passer par les migrations Laravel.*
