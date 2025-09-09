<?php

namespace App\Enums;

enum UserType: string
{
    case CLIENT = 'client';
    case LIVREUR = 'livreur';
    case ADMIN = 'admin';
    case BACKOFFICE = 'backoffice';
    case AGENCE = 'agence';

    public function label(): string
    {
        return match($this) {
            self::CLIENT => 'Client',
            self::LIVREUR => 'Livreur',
            self::ADMIN => 'Administrateur',
            self::BACKOFFICE => 'Back-Office',
            self::AGENCE => 'Agence partenaire',
        };
    }
}