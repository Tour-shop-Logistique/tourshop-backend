<?php

namespace App\Enums;

enum ExpeditionStatus: string
{
    case EN_ATTENTE = 'en_attente'; //Par le client
    case ACCEPTED = 'accepted'; //Par l'agence de depart
    case REFUSED = 'refused'; //Par l'agence de depart
    case CANCELLED = 'cancelled'; //Par le client
    case EN_COURS_ENLEVEMENT = 'en_cours_enlevement'; //Par l'agence de depart
    case EN_COURS_DEPOT = 'en_cours_depot'; //Par le livreur local
    case RECU_AGENCE_DEPART = 'recu_agence_depart'; //Par l'agence de depart
    case EN_TRANSIT_ENTREPOT = 'en_transit_entrepot'; //Par l'agence de depart
    case DEPART_EXPEDITION_SUCCES = 'depart_expedition_succes'; //Par le backoffice de depart
    case ARRIVEE_EXPEDITION_SUCCES = 'arrivee_expedition_succes'; //Par le backoffice de destination
    case RECU_AGENCE_DESTINATION = 'recu_agence_destination'; //Par l'agence de destination
    case EN_COURS_LIVRAISON = 'en_cours_livraison'; //Par l'agence de destination
    case TERMINED = 'termined'; //Par le livreur etranger ou l'agence de destination

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente de validation',
            self::ACCEPTED => 'Validé par l\'agence',
            self::REFUSED => 'Refusé par l\'agence',
            self::CANCELLED => 'Annulée par le client',
            self::EN_COURS_ENLEVEMENT => 'Enlèvement en cours',
            self::EN_COURS_DEPOT => 'En cours de depot',
            self::RECU_AGENCE_DEPART => 'Reçu à l\'agence depart',
            self::EN_TRANSIT_ENTREPOT => 'En transit vers l\'entrepôt',
            self::DEPART_EXPEDITION_SUCCES => 'Depart expedition avec succes',
            self::ARRIVEE_EXPEDITION_SUCCES => 'Arrivée expedition avec succes',
            self::RECU_AGENCE_DESTINATION => 'Reçu agence destination',
            self::EN_COURS_LIVRAISON => 'En cours de livraison',
            self::TERMINED => 'Terminé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EN_ATTENTE => '#FFA500', // Orange
            self::ACCEPTED => '#32CD32', // LimeGreen
            self::REFUSED => '#FF0000', // Red
            self::CANCELLED => '#FF0000', // Red
            self::EN_COURS_ENLEVEMENT => '#1E90FF', // DodgerBlue
            self::EN_COURS_DEPOT => '#16ac84ff', // RoyalBlue
            self::RECU_AGENCE_DEPART => '#4169E1', // RoyalBlue
            self::EN_TRANSIT_ENTREPOT => '#9370DB', // MediumPurple
            self::DEPART_EXPEDITION_SUCCES => '#8A2BE2', // BlueViolet
            self::ARRIVEE_EXPEDITION_SUCCES => '#4B0082', // Indigo
            self::RECU_AGENCE_DESTINATION => '#00CED1', // DarkTurquoise
            self::EN_COURS_LIVRAISON => '#FF4500', // OrangeRed
            self::TERMINED => '#008000', // Green
        };
    }
}