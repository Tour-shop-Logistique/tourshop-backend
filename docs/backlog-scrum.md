# Product Backlog - Projet TourShop (Méthode SCRUM)

Ce document répertorie l'ensemble des fonctionnalités et travaux à réaliser pour le projet TourShop, organisés par Epics et User Stories.

## 🟢 Epic 1 : Gestion des Utilisateurs & Authentification
*   **US.1.1** : En tant qu'utilisateur, je veux pouvoir m'inscrire et me connecter avec mon numéro de téléphone ou email.
*   **US.1.2** : En tant qu'administrateur, je veux pouvoir créer et gérer les comptes des agences partenaires.
*   **US.1.3** : En tant qu'administrateur d'agence, je veux pouvoir gérer mon équipe de livreurs.
*   **US.1.4** : En tant qu'utilisateur, je veux pouvoir mettre à jour mes informations de profil et mes coordonnées.

## 📦 Epic 2 : Core Logistics (Expéditions & Colis)
*   **US.2.1** : En tant qu'agence, je veux pouvoir créer une expédition complète pour un client (émetteur et destinataire).
*   **US.2.2** : En tant qu'utilisateur (Client/Agence), je veux pouvoir ajouter plusieurs colis à une même expédition.
*   **US.2.3** : En tant qu'agence, je veux pouvoir générer une référence unique pour chaque expédition.
*   **US.2.4** : En tant que client, je veux pouvoir initier une demande d'expédition depuis l'application.

## 💰 Epic 3 : Tarification Dynamique & Simulation
*   **US.3.1** : En tant qu'administrateur Backoffice, je veux définir les tarifs de base par zone pour les expéditions simples (LD).
*   **US.3.2** : En tant qu'administrateur Backoffice, je veux définir les tarifs de groupage par catégorie de produit (Afrique, CA, DHD).
*   **US.3.3** : En tant qu'agence, je veux pouvoir personnaliser mon pourcentage de prestation sur les tarifs de base.
*   **US.3.4** : En tant qu'utilisateur, je veux simuler le coût total d'une expédition avant sa création finale.
*   **US.3.5** : En tant que système, je veux calculer automatiquement le poids volumétrique pour appliquer le tarif le plus avantageux.

## 🔄 Epic 4 : Workflow & Suivi (Tracking)
*   **US.4.1** : En tant qu'utilisateur, je veux suivre l'avancement de mon expédition via un code de tracking.
*   **US.4.2** : En tant que livreur, je veux pouvoir mettre à jour le statut d'une expédition (Enlèvement effectué, Livré).
*   **US.4.3** : En tant qu'agence, je veux gérer les étapes internes (Arrivée en entrepôt, Expédié, Arrivé à destination).
*   **US.4.4** : En tant que système, je veux générer un code de validation de réception sécurisé pour le destinataire.

## 💸 Epic 5 : Commissions & Finances
*   **US.5.1** : En tant qu'administrateur, je veux configurer les commissions globales pour TourShop, les agences et les livreurs.
*   **US.5.2** : En tant qu'agence, je veux consulter mon solde de commissions et l'historique de mes gains.
*   **US.5.3** : En tant que système, je veux calculer automatiquement les commissions lors de la validation d'une expédition.
*   **US.5.4** : En tant qu'agence, je veux gérer les frais annexes (emballage, stockage, retard).

## 📊 Epic 6 : Administration & Reporting
*   **US.6.1** : En tant qu'administrateur, je veux avoir une vue d'ensemble (Dashboard) sur toutes les expéditions en cours.
*   **US.6.2** : En tant qu'administrateur, je veux gérer les pays et les zones géographiques desservis.
*   **US.6.3** : En tant qu'utilisateur, je veux recevoir des notifications (Push/SMS/Email) lors du changement de statut de mon colis.

---

## 📅 Suggestions de Sprints (Exemple)

### Sprint 1 : Fondations & Tarification (En cours/Terminé)
*   Mise en place de la base de données.
*   Refactorisation des tarifs simples et groupés.
*   Mise en place du service de tarification.
*   API de simulation de tarifs.

### Sprint 2 : Workflow d'Expédition & Colis
*   Création d'expédition avec multi-colis.
*   Gestion des contacts (Expéditeur/Destinataire en JSON).
*   Assignation des livreurs.

### Sprint 3 : Tracking & Statuts
*   Moteur de changement de statut.
*   Génération des références et codes de suivi.
*   Système de validation par code.

### Sprint 4 : Commissions & Dashboard
*   Calcul automatique des commissions.
*   Interface d'administration Backoffice.
*   Gestion des paramètres globaux.
