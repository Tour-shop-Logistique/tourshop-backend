# Workflow d'Expédition Tour Shop

## Vue d'ensemble

Le système d'expédition Tour Shop gère le cycle de vie complet des colis, de la création par le client jusqu'à la livraison finale au destinataire, avec validation sécurisée.

## Acteurs du Système

### 1. Client
- Crée les expéditions
- Reçoit le code de validation
- Transmet le code au destinataire
- Peut annuler les expéditions en attente/acceptées

### 2. Agence
- Valide/refuse les expéditions
- Gère les tarifs
- Organise la logistique
- Assigne les livreurs

### 3. Livreur
- Effectue les enlèvements et livraisons
- Valide la réception avec le code
- Met à jour les statuts en temps réel

### 4. Destinataire
- Reçoit le colis
- Fournit le code de validation au livreur

## Cycle de Vie d'une Expédition

### Étape 1: Création (Client)

```php
// ClientExpeditionController@initiate
POST /api/client/expeditions/initiate
```

**Données requises:**
- `agence_id`: UUID de l'agence choisie
- `zone_depart_id`: Zone de départ
- `zone_destination_id`: Zone de destination  
- `mode_expedition`: "simple" ou "groupage"

**Processus:**
1. Validation des données
2. Création de l'expédition avec statut `EN_ATTENTE`
3. **Génération automatique du code de validation** (6 caractères alphanumériques)
4. Envoi immédiat du code au client (SMS + Email)
5. Notification à l'agence pour validation

**Champs générés automatiquement:**
- `id`: UUID unique
- `reference`: Référence unique (TS-2025-XXXXX)
- `code_suivi_expedition`: Code suivi (8 caractères)
- `code_validation_reception`: Code validation (6 caractères)
- `statut_expedition`: EN_ATTENTE
- `statut_paiement`: EN_ATTENTE

### Étape 2: Ajout des Articles

```php
// ExpeditionArticleController@add
POST /api/expeditions/{expeditionId}/articles
```

**Processus:**
1. Client ajoute les articles un par un
2. Calcul automatique du poids/volume total
3. Mise à jour du tarif en temps réel
4. Possibilité de simulation avant validation

**Validation:**
- Seules les expéditions EN_ATTENTE ou ACCEPTED peuvent être modifiées
- Calcul automatique du tarif après chaque ajout/modification

### Étape 3: Validation Agence

```php
// AgenceExpeditionController@validate
PUT /api/agence/expeditions/{expedition}/validate
```

**Décisions possibles:**
- ✅ **ACCEPTED**: Expédition validée
- ❌ **REFUSED**: Expédition refusée (avec motif)

**Processus de validation:**
1. Vérification des articles et tarifs
2. Confirmation des disponibilités
3. Assignation d'un livreur (si disponible)
4. Notification au client du résultat

### Étape 4: Enlèvement à Domicile (Optionnel)

```php
// ClientExpeditionController@initiate - Option enlèvement domicile
// LivreurExpeditionController@demarrerEnlevement
POST /api/livreur/enlevement/{expedition}/start
```

**Option enlèvement domicile:**
- Client choisit "enlèvement domicile" lors de la création
- **Calcul automatique des frais** selon distance domicile-agence:
  - Grille tarifaire par intervalle de distance (ex: 0-5km, 5-10km, 10-20km, 20km+)
  - Calcul via API de géolocalisation (Google Maps/OSM)

**Processus d'enlèvement:**
1. **Assignation livreur** pour enlèvement domicile
2. **Livreur se rend au client** pour récupérer le colis
3. **Livraison à l'agence** pour vérification
4. **Mise à jour `date_enlevement_reelle`** et statut `EN_COURS_ENLEVEMENT`

### Étape 4b: Vérification Agence & Finalisation Tarif

```php
// AgenceExpeditionController@confirmerReceptionColis
POST /api/agence/expeditions/{expedition}/confirm-reception
```

**Processus de vérification:**
1. **Contrôle du colis** reçu du domicile client
2. **Ajout des frais supplémentaires**:
   - Frais d'emballage (selon type/complexité)
   - Assurance optionnelle
   - Poids/volume réel vérifié
3. **Calcul du montant final** d'expédition
4. **Mise à jour tarif** et notification client du montant final
5. Changement statut vers `RECU_AGENCIA`

### Étape 5: Réception Agence

```php
// LivreurExpeditionController@confirmerReceptionAgence
POST /api/livreur/reception-agence/{expedition}/confirm
```

**Processus:**
1. Livreur confirme la réception à l'agence
2. Mise à jour `date_livraison_agence`
3. Changement de statut vers `RECU_AGENCIA`
4. Préparation pour l'expédition internationale

### Étape 5: Expédition vers Entrepôt

```php
// AgenceExpeditionController@expedierVersEntrepot
POST /api/agence/expeditions/{expedition}/ship-to-warehouse
```

**Processus:**
1. **Regroupement des colis** pour expédition
2. **Départ vers l'entrepôt** (aéroport/bateau)
3. Mise à jour `date_deplacement_entrepot`
4. Statut: `EN_TRANSIT_ENTREPOT`

### Étape 6: Traitement Entrepôt & Chargement

```php
// EntrepotController@recevoirColis
POST /api/entrepot/expeditions/{expedition}/receive
// EntrepotController@confirmerChargement
POST /api/entrepot/vols/{volId}/confirm-loading
```

**Processus entrepôt:**
1. **Réception des colis** à l'entrepôt
2. **Vérification documentation** (douanes, poids)
3. **Assignation au vol/bateau** correspondant
4. **Chargement dans l'appareil** par l'équipe entrepôt
5. **Confirmation du départ** de l'appareil
6. Mise à jour `date_expedition_depart`
7. Statut: `EXPEDITION_DEPART`

### Étape 7: Arrivée Destination & Traitement

```php
// EntrepotController@confirmerArriveeDestination
POST /api/entrepot/arrivee/{volId}/confirm-arrival
```

**Processus arrivée:**
1. **Réception de l'appareil** à destination
2. **Déchargement des colis** par l'équipe locale
3. **Vérification douanière** si nécessaire
4. **Mise à jour `date_expedition_arrivee`**
5. Statut: `EXPEDITION_ARRIVEE`
6. **Acheminement vers l'agence distante**

### Étape 8: Réception Agence Destination

```php
// AgenceExpeditionController@receptionColis
POST /api/agence/expeditions/{expedition}/reception
```

**Processus:**
1. Réception par l'agence partenaire
2. Mise à jour `date_reception_agence`
3. Statut: `RECU_AGENCIA_DESTINATION`
4. **Détermination du mode de remise** au destinataire

### Étape 9: Livraison à Domicile (Optionnel)

```php
// AgenceExpeditionController@configurerLivraisonDomicile
POST /api/agence/expeditions/{expedition}/configure-home-delivery
```

**Option livraison domicile:**
- **Client a choisi livraison domicile** lors de la création
- **Calcul des frais de livraison** par l'agence destination:
  - Grille tarifaire selon distance agence-destinataire
  - Calcul via API de géolocalisation
  - Ajout au montant total de l'expédition

**Processus:**
1. **Assignation livreur** pour livraison finale
2. **Notification destinataire** (si contact disponible)
3. **Démarrage livraison** avec validation sécurisée

### Étape 10: Retrait en Agence (Alternative)

```php
// AgenceExpeditionController@preparerRetraitAgence
POST /api/agence/expeditions/{expedition}/prepare-agency-pickup
```

**Option retrait agence:**
- **Client n'a PAS choisi livraison domicile**
- **Destinataire doit se déplacer** à l'agence
- **Délai de retrait défini** par l'agence (ex: 7 jours, 14 jours)

**Processus:**
1. **Calcul date limite retrait**: `date_reception_agence + délai_agence`
2. **Notification destinataire** avec date limite
3. **Mise en attente de retrait**: statut `EN_ATTENTE_RETRAIT`

### Étape 11: Frais de Retard (si applicable)

```php
// AgenceExpeditionController@appliquerFraisRetard
POST /api/agence/expeditions/{expedition}/apply-late-fees
```

**Gestion des frais de retard:**
- **Destinataire ne récupère pas** dans le délai imparti
- **Frais de retard appliqués** automatiquement:
  - Calcul par jour de retard (ex: 500 FCFA/jour)
  - Notification au destinataire et client
  - Mise à jour du montant total

**Processus:**
1. **Vérification dépassement délai**
2. **Calcul frais** = (jours_retard × tarif_journalier)
3. **Mise à jour montant_total_expedition**
4. **Notification** des nouvelles conditions

### Étape 12: Validation Réception Finale

#### Option A: Livraison à Domicile

```php
// LivreurExpeditionController@validerLivraisonAvecCode
POST /api/livreur/livraison/{expedition}/validate
```

**Processus de validation sécurisé:**
1. **Le livreur demande le code** au destinataire
2. **Le destinataire fournit le code** reçu du client
3. **Le livreur saisit le code** dans l'application

**Validation:**
```php
$request->validate([
    'code_validation' => 'required|string|size:6',
]);

if ($request->code_validation !== $expedition->code_validation_reception) {
    return response()->json([
        'success' => false,
        'message' => 'Code de réception incorrect'
    ], 422);
}
```

#### Option B: Retrait en Agence

```php
// AgenceExpeditionController@confirmerRetraitClient
POST /api/agence/expeditions/{expedition}/confirm-pickup
```

**Processus de retrait:**
1. **Destinataire se présente** à l'agence
2. **Vérification identité** et code de validation
3. **Remise du colis** contre signature
4. **Mise à jour statut** final

**Résultat final (toutes options):**
- Mise à jour `date_reception_client`
- Statut final: `LIVRE`
- `code_validation_reception` = null (nettoyage)
- **Notification au client** de la livraison effective

## Système de Tarification

### Calcul Automatique

```php
// ExpeditionTarificationService
public function calculerTarifExpedition(Expedition $expedition)
{
    // 1. Déterminer le mode (simple/groupage)
    // 2. Calculer poids/volume total
    // 3. Trouver les tarifs applicables
    // 4. Appliquer les pourcentages
    // 5. Calculer le montant final
}
```

### Modes d'Expédition

#### Mode Simple
- Basé sur l'indice de poids/volume
- Tarif unique par zone
- Calcul: `montant_base + (montant_base * pourcentage_agence / 100)`

#### Mode Groupage
- Basé sur la catégorie de produits
- Tarifs par catégorie + mode de transport
- Calcul par zone avec modes multiples (avion, bateau, accompagné)

### Frais Additionnels

- **Enlèvement domicile**: 
  - Calcul par tranche de distance domicile-agence
  - Grille tarifaire: 0-5km, 5-10km, 10-20km, 20km+
  - Ex: 1000 FCFA (0-5km), 1500 FCFA (5-10km), 2000 FCFA (10-20km), 3000 FCFA (20km+)

- **Livraison domicile**: 
  - Calcul par tranche de distance agence-destinataire
  - Grille tarifaire similaire à l'enlèvement
  - Défini par l'agence de destination

- **Emballage**: Coût par type/complexité
  - Standard: 500 FCFA
  - Fragile: 1000 FCFA  
  - Surdimensionné: 2000 FCFA+

- **Frais de retard retrait**: 
  - Calcul par jour de retard après délai imparti
  - Ex: 500 FCFA par jour de retard
  - Appliqué automatiquement après dépassement délai

## Statuts d'Expédition

### Statuts Principaux
- `EN_ATTENTE`: En attente de validation agence
- `ACCEPTED`: Validé par l'agence
- `REFUSED`: Refusé par l'agence
- `CANCELLED`: Annulé par le client
- `EN_COURS_ENLEVEMENT`: En cours d'enlèvement domicile
- `RECU_AGENCIA`: Reçu à l'agence (après vérification)
- `EN_TRANSIT_ENTREPOT`: En transit vers entrepôt
- `EXPEDITION_DEPART`: Parti du pays (chargé dans appareil)
- `EXPEDITION_ARRIVEE`: Arrivé destination (déchargé)
- `RECU_AGENCIA_DESTINATION`: Reçu agence destination
- `EN_ATTENTE_RETRAIT`: En attente de retrait en agence
- `EN_LIVRAISON`: En cours de livraison domicile
- `LIVRE`: Livré avec succès (domicile ou agence)

### Statuts de Paiement
- `EN_ATTENTE`: En attente de paiement
- `PAYE`: Payé
- `PARTIELLEMENT_PAYE`: Partiellement payé
- `REMBOURSE`: Remboursé

## Notifications Automatiques

### Client
- ✅ Création expédition + code validation
- ✅ Confirmation enlèvement domicile (si choisi)
- ✅ Validation/refus par l'agence
- ✅ Montant final avec frais additionnels
- ✅ Changements de statut majeurs
- ✅ Livraison effectuée
- ⚠️ Notification frais retard (si applicable)

### Agence
- ✅ Nouvelle expédition à valider
- ✅ Confirmation réception colis (enlèvement domicile)
- ✅ Finalisation tarif avec frais emballage
- ✅ Expédition vers entrepôt
- ✅ Réception colis destination
- ✅ Configuration livraison domicile
- ✅ Gestion délais retrait et frais retard
- ✅ Notifications arrivée/départ

### Livreur
- ✅ Assignations nouvelles missions (enlèvement/livraison)
- ✅ Détails des enlèvements domicile
- ✅ Instructions de livraison finale
- ✅ Validation codes de réception
- ✅ Optimisation de tournée

### Destinataire (si contact disponible)
- ✅ Préparation livraison
- ✅ Date limite de retrait (si agence)
- ✅ Instructions de réception
- ⚠️ Notification frais retard (si dépassement délai)

### Équipe Entrepôt
- ✅ Réception colis à expédier
- ✅ Chargement dans appareil (avion/bateau)
- ✅ Confirmation départ/arrivée
- ✅ Déchargement et acheminement vers agences

## Sécurité et Validation

### Codes de Sécurité
- **Code Validation**: 6 caractères alphanumériques
- **Code Suivi**: 8 caractères pour tracking public
- **Référence**: Format TS-YYYY-XXXXX

### Contrôles d'Accès
- Client ne voit que ses expéditions
- Agence ne voit que ses expéditions assignées
- Livreur ne voit que ses missions du jour
- Validation systématique des permissions

### Géolocalisation
- Tracking automatique des positions
- Validation des zones d'intervention
- Optimisation des tournées

## API Endpoints

### Client
```
GET    /api/client/expeditions              // Lister
POST   /api/client/expeditions/initiate     // Créer
GET    /api/client/expeditions/{id}         // Détails
PUT    /api/client/expeditions/{id}/cancel  // Annuler
POST   /api/client/expeditions/simulate     // Simuler tarif
GET    /api/client/expeditions/statistics   // Statistiques
```

### Articles
```
GET    /api/expeditions/{id}/articles       // Lister
POST   /api/expeditions/{id}/articles       // Ajouter
PUT    /api/expeditions/{id}/articles/{aid} // Modifier
DELETE /api/expeditions/{id}/articles/{aid} // Supprimer
POST   /api/expeditions/simulate            // Simuler avec articles
```

### Agence
```
GET    /api/agence/expeditions              // Lister
PUT    /api/agence/expeditions/{id}/validate// Valider
PUT    /api/agence/expeditions/{id}/refuse  // Refuser
POST   /api/agence/expeditions/{id}/ship    // Expédier
```

### Livreur
```
GET    /api/livreur/expeditions             // Missions du jour
POST   /api/livreur/enlevement/{id}/start   // Démarrer enlèvement
POST   /api/livraison/{id}/start           // Démarrer livraison
POST   /api/livraison/{id}/validate        // Valider avec code
```

## Tracking Public

### Code Suivi
- Accessible sans authentification
- Informations limitées (statut, dates principales)
- QR code générable pour partage facile

### Exemple de tracking
```
GET /api/public/tracking/{code_suivi}
{
  "reference": "TS-2025-12345",
  "statut": "En cours de livraison",
  "date_estimee": "2025-11-28 14:00",
  "localisation": "Paris, France",
  "historique": [...]
}
```

## Gestion des Erreurs

### Codes d'Erreur
- `403`: Non autorisé
- `404`: Ressource non trouvée
- `422`: Validation échouée
- `429`: Trop de tentatives

### Messages Types
- "Expédition non trouvée"
- "Code de validation incorrect"
- "Trop de tentatives, réessayez plus tard"
- "Cette expédition ne peut plus être modifiée"

## Performance et Optimisation

### Cache
- Tarifs calculés mis en cache (24h)
- Zones géographiques (1 semaine)
- Statistiques clients (1 heure)

### Indexation DB
- `client_id + statut + created_at`
- `agence_id + statut`
- `livreur_id + date_livraison`
- `code_suivi` (unique)

### File d'Attente
- Notifications SMS/Email asynchrones
- Calculs de tarification en background
- Génération de rapports différés

## Maintenance et Monitoring

### Logs Essentiels
- Créations/modifications expéditions
- Tentatives de validation codes
- Erreurs de tarification
- Performances des API

### Métriques
- Temps moyen de validation agence
- Taux de réussite livraison
- Temps de transit moyen
- Satisfaction client

---

*Ce document décrit l'intégralité du workflow d'expédition Tour Shop. Pour toute question ou mise à jour, contacter l'équipe de développement.*
