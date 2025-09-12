<?php

namespace App\Enums;

enum TypeColis: string
{
    case DOCUMENT = 'document';
    case COLIS_STANDARD = 'colis_standard';
    case COLIS_FRAGILE = 'colis_fragile';
    case COLIS_VOLUMINEUX = 'colis_volumineux';
    case PRODUIT_ALIMENTAIRE = 'produit_alimentaire';
    case ELECTRONIQUE = 'electronique';
    case VETEMENT = 'vetement';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::DOCUMENT => 'Document',
            self::COLIS_STANDARD => 'Colis Standard',
            self::COLIS_FRAGILE => 'Colis Fragile',
            self::COLIS_VOLUMINEUX => 'Colis Volumineux',
            self::PRODUIT_ALIMENTAIRE => 'Produit Alimentaire',
            self::ELECTRONIQUE => 'Électronique',
            self::VETEMENT => 'Vêtement',
            self::AUTRE => 'Autre',
        };
    }
}
