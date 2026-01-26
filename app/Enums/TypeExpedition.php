<?php

namespace App\Enums;

enum TypeExpedition: string
{
    case LD = 'simple';
    case GROUPAGE_AFRIQUE = 'groupage_afrique';
    case GROUPAGE_CA = 'groupage_ca';
    case GROUPAGE_DHD_AERIEN = 'groupage_dhd_aerien';
    case GROUPAGE_DHD_MARITIME = 'groupage_dhd_maritime';

    public function label(): string
    {
        return match ($this) {
            self::LD => 'Type Livraison Domicile',
            self::GROUPAGE_AFRIQUE => 'Type Groupage Afrique',
            self::GROUPAGE_CA => 'Type Groupage CA',
            self::GROUPAGE_DHD_AERIEN => 'Type Groupage DHD',
            self::GROUPAGE_DHD_MARITIME => 'Type Groupage DHD',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LD => 'Expédition directe et individuelle',
            self::GROUPAGE_AFRIQUE => 'Expédition groupée avec autres colis',
            self::GROUPAGE_CA => 'Expédition groupée avec autres colis',
            self::GROUPAGE_DHD_AERIEN => 'Expédition groupée avec autres colis',
            self::GROUPAGE_DHD_MARITIME => 'Expédition groupée avec autres colis',
        };
    }
}
