<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Zone;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        // Zone 1 - Afrique de l'Ouest
        Zone::create([
            'nom' => 'Zone 1 - Afrique de l\'Ouest',
            'code' => 'Z1_AFRIQUE_OUEST',
            'pays' => [
                'Côte d\'Ivoire',
                'Ghana',
                'Sénégal',
                'Mali',
                'Burkina Faso',
                'Niger',
                'Guinée',
                'Bénin',
                'Togo',
                'Liberia',
                'Sierra Leone',
                'Gambie',
                'Guinée-Bissau',
                'Cap-Vert',
                'Mauritanie'
            ],
            'actif' => true
        ]);

        // Zone 2 - Afrique de l'Est
        Zone::create([
            'nom' => 'Zone 2 - Afrique de l\'Est',
            'code' => 'Z2_AFRIQUE_EST',
            'pays' => [
                'Kenya',
                'Tanzanie',
                'Éthiopie',
                'Ouganda',
                'Rwanda',
                'Burundi',
                'Somalie',
                'Djibouti',
                'Érythrée',
                'Soudan',
                'Soudan du Sud'
            ],
            'actif' => true
        ]);

        // Zone 3 - Afrique Centrale
        Zone::create([
            'nom' => 'Zone 3 - Afrique Centrale',
            'code' => 'Z3_AFRIQUE_CENTRALE',
            'pays' => [
                'Cameroun',
                'République Centrafricaine',
                'Tchad',
                'République Démocratique du Congo',
                'Congo',
                'Gabon',
                'Guinée Équatoriale',
                'São Tomé-et-Príncipe'
            ],
            'actif' => true
        ]);

        // Zone 4 - Afrique du Sud
        Zone::create([
            'nom' => 'Zone 4 - Afrique du Sud',
            'code' => 'Z4_AFRIQUE_SUD',
            'pays' => [
                'Afrique du Sud',
                'Namibie',
                'Botswana',
                'Zimbabwe',
                'Zambie',
                'Malawi',
                'Mozambique',
                'Eswatini',
                'Lesotho',
                'Angola',
                'Madagascar',
                'Maurice',
                'Seychelles',
                'Comores'
            ],
            'actif' => true
        ]);

        // Zone 5 - Afrique du Nord
        Zone::create([
            'nom' => 'Zone 5 - Afrique du Nord',
            'code' => 'Z5_AFRIQUE_NORD',
            'pays' => [
                'Maroc',
                'Algérie',
                'Tunisie',
                'Libye',
                'Égypte'
            ],
            'actif' => true
        ]);

        // Zone 6 - Europe de l'Ouest
        Zone::create([
            'nom' => 'Zone 6 - Europe de l\'Ouest',
            'code' => 'Z6_EUROPE_OUEST',
            'pays' => [
                'France',
                'Allemagne',
                'Royaume-Uni',
                'Espagne',
                'Italie',
                'Pays-Bas',
                'Belgique',
                'Suisse',
                'Autriche',
                'Portugal',
                'Irlande',
                'Luxembourg'
            ],
            'actif' => true
        ]);

        // Zone 7 - Europe de l'Est
        Zone::create([
            'nom' => 'Zone 7 - Europe de l\'Est',
            'code' => 'Z7_EUROPE_EST',
            'pays' => [
                'Pologne',
                'République Tchèque',
                'Hongrie',
                'Slovaquie',
                'Roumanie',
                'Bulgarie',
                'Croatie',
                'Slovénie',
                'Serbie',
                'Ukraine',
                'Russie',
                'Biélorussie'
            ],
            'actif' => true
        ]);

        // Zone 8 - Asie
        Zone::create([
            'nom' => 'Zone 8 - Asie',
            'code' => 'Z8_ASIE',
            'pays' => [
                'Chine',
                'Japon',
                'Inde',
                'Corée du Sud',
                'Thaïlande',
                'Vietnam',
                'Singapour',
                'Malaisie',
                'Indonésie',
                'Philippines',
                'Turquie',
                'Émirats Arabes Unis',
                'Arabie Saoudite'
            ],
            'actif' => true
        ]);
    }
}
