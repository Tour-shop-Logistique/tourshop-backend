<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CommissionSetting;

class CommissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $commissions = [
            [
                'key' => 'commission_livreur_enlevement',
                'value' => 85.00,
                'type' => 'pourcentage',
                'description' => "Commission perçue par le livreur sur les frais d'enlevement à domicile",
                'is_active' => true,
            ],
            [
                'key' => 'commission_agence_enlevement',
                'value' => 15.00,
                'type' => 'pourcentage',
                'description' => "Commission perçue par l'agence sur les frais d'enlevement à domicile",
                'is_active' => true,
            ],
            [
                'key' => 'commission_livreur_livraison',
                'value' => 90.00,
                'type' => 'pourcentage',
                'description' => "Commission perçue par le livreur sur les frais de livraison à domicile",
                'is_active' => true,
            ],
            [
                'key' => 'commission_agence_livraison',
                'value' => 10.00,
                'type' => 'pourcentage',
                'description' => "Commission perçue par l'agence sur les frais de livraison à domicile",
                'is_active' => true,
            ],
            [
                'key' => 'commission_agence_retard',
                'value' => 40.00,
                'type' => 'pourcentage',
                'description' => "Commission perçue par l'agence sur les frais de retard de retrait",
                'is_active' => true,
            ],
            [
                'key' => 'commission_tourshop_retard',
                'value' => 60.00,
                'type' => 'pourcentage',
                'description' => "Commission perçue par TourShop sur les frais de retard de retrait",
                'is_active' => true,
            ],
        ];

        foreach ($commissions as $commission) {
            CommissionSetting::updateOrCreate(
                ['key' => $commission['key']],
                $commission
            );
        }
    }
}
