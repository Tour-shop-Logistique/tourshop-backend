<?php

namespace App\Enums;

enum ModeExpedition: string
{
    case SIMPLE = 'simple';
    case GROUPAGE = 'groupage';

    public function label(): string
    {
        return match ($this) {
            self::SIMPLE => 'Mode Simple',
            self::GROUPAGE => 'Mode Groupage',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SIMPLE => 'Expédition directe et individuelle',
            self::GROUPAGE => 'Expédition groupée avec autres colis',
        };
    }
}
