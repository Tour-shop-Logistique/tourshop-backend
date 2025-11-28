<?php

namespace App\Enums;

enum ExpeditionStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case ACCEPTED = 'accepted';
    case REFUSED = 'refused';
    case EN_COURS_ENLEVEMENT = 'en_cours_enlevement';
    case RECU_AGENCIA = 'recu_agencia';
    case IN_PROGRESS = 'in_progress';
    case EN_TRANSIT_ENTREPOT = 'en_transit_entrepot';
    case EXPEDITION_DEPART = 'expedition_depart';
    case EXPEDITION_ARRIVEE = 'expedition_arrivee';
    case RECU_AGENCIA_DESTINATION = 'recu_agencia_destination';
    case EN_ATTENTE_RETRAIT = 'en_attente_retrait';
    case EN_LIVRAISON = 'en_livraison';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case LIVRE = 'livre';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente de validation',
            self::ACCEPTED => 'Validé par l\'agence',
            self::REFUSED => 'Refusé',
            self::EN_COURS_ENLEVEMENT => 'Enlèvement en cours',
            self::RECU_AGENCIA => 'Reçu à l\'agence',
            self::IN_PROGRESS => 'En cours de traitement',
            self::EN_TRANSIT_ENTREPOT => 'En transit vers l\'entrepôt',
            self::EXPEDITION_DEPART => 'Expédié (Départ)',
            self::EXPEDITION_ARRIVEE => 'Arrivé à destination',
            self::RECU_AGENCIA_DESTINATION => 'Reçu agence destination',
            self::EN_ATTENTE_RETRAIT => 'En attente de retrait',
            self::EN_LIVRAISON => 'En livraison',
            self::SHIPPED => 'Expédié',
            self::DELIVERED => 'Livré',
            self::LIVRE => 'Livré (Final)',
            self::CANCELLED => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EN_ATTENTE => '#FFA500', // Orange
            self::ACCEPTED => '#32CD32', // LimeGreen
            self::REFUSED => '#FF0000', // Red
            self::EN_COURS_ENLEVEMENT => '#1E90FF', // DodgerBlue
            self::RECU_AGENCIA => '#4169E1', // RoyalBlue
            self::IN_PROGRESS => '#87CEEB', // SkyBlue
            self::EN_TRANSIT_ENTREPOT => '#9370DB', // MediumPurple
            self::EXPEDITION_DEPART => '#8A2BE2', // BlueViolet
            self::EXPEDITION_ARRIVEE => '#4B0082', // Indigo
            self::RECU_AGENCIA_DESTINATION => '#00CED1', // DarkTurquoise
            self::EN_ATTENTE_RETRAIT => '#FF8C00', // DarkOrange
            self::EN_LIVRAISON => '#FF4500', // OrangeRed
            self::SHIPPED => '#2E8B57', // SeaGreen
            self::DELIVERED => '#008000', // Green
            self::LIVRE => '#006400', // DarkGreen
            self::CANCELLED => '#808080', // Gray
        };
    }
}