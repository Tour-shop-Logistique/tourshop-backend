<?php

namespace App\Enums;

enum StatutPaiement: string
{
    case EN_ATTENTE = 'en_attente';
    case PAYE = 'paye';
    case REFUSE = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::PAYE => 'Payé',
            self::REFUSE => 'Refusé',
        };
    }
}
