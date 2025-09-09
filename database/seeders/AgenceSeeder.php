<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Agence;
use App\Enums\UserType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str; // Nécessaire pour les UUIDs

class AgenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer un utilisateur de type 'AGENCE'
        $userAgence = User::create([
            'id' => (string) Str::uuid(), // Générer un UUID pour l'utilisateur
            'nom' => 'Baby',
            'prenoms' => 'Occase',
            'telephone' => '0707070707',
            'email' => 'babyoccase@example.com',
            'password' => Hash::make('password'), // Mot de passe 'password'
            'type' => UserType::AGENCE, // Utilisation de l'enum
            'actif' => true,
        ]);

        // Créer une agence associée à cet utilisateur
        Agence::create([
            'id' => (string) Str::uuid(), // Générer un UUID pour l'agence
            'user_id' => $userAgence->id, // Lier à l'ID UUID de l'utilisateur créé
            'nom_agence' => 'Agence Baby Occase Express',
            'adresse' => "Adjame Williamsville, Abidjan",
            'ville' => 'Abidjan',
            'commune' => 'Adjame',
            'latitude' => 5.3400000,
            'longitude' => -4.0200000,
            'horaires' => json_encode([
                ['jour' => 'Lundi', 'ouverture' => '08:00', 'fermeture' => '17:00'],
                ['jour' => 'Mardi', 'ouverture' => '08:00', 'fermeture' => '17:00']
            ]),
            'zone_couverture_km' => 20,
        ]);

        $this->command->info('Utilisateur agence et agence créés avec succès !');
    }
}