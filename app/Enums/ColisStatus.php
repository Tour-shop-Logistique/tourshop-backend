<?php

namespace App\Enums;

enum ColisStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case VALIDE = 'valide';
    case EN_ENLEVEMENT = 'en_enlevement';
    case RECUPERE = 'recupere';
    case EN_TRANSIT = 'en_transit';
    case EN_AGENCE = 'en_agence';
    case EN_LIVRAISON = 'en_livraison';
    case LIVRE = 'livre';
    case ECHEC = 'echec';
    case ANNULE = 'annule';

    public function label(): string
    {
        return match($this) {
            self::EN_ATTENTE => 'En attente de validation',
            self::VALIDE => 'Validé',
            self::EN_ENLEVEMENT => 'Enlèvement en cours',
            self::RECUPERE => 'Colis récupéré',
            self::EN_TRANSIT => 'En transit',
            self::EN_AGENCE => 'Arrivé à l\'agence',
            self::EN_LIVRAISON => 'En livraison',
            self::LIVRE => 'Livré',
            self::ECHEC => 'Échec de livraison',
            self::ANNULE => 'Annulé',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::EN_ATTENTE => '#FFA500',
            self::VALIDE => '#32CD32',
            self::EN_ENLEVEMENT => '#1E90FF',
            self::RECUPERE => '#4169E1',
            self::EN_TRANSIT => '#9932CC',
            self::EN_AGENCE => '#FF6347',
            self::EN_LIVRAISON => '#DC143C',
            self::LIVRE => '#008000',
            self::ECHEC => '#FF0000',
            self::ANNULE => '#808080',
        };
    }
}