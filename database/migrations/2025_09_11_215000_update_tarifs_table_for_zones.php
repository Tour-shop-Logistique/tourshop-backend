<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifs', function (Blueprint $table) {
            // Zones de départ et d'arrivée
            $table->uuid('zone_depart_id')->nullable()->after('mode_expedition');
            $table->uuid('zone_arrivee_id')->nullable()->after('zone_depart_id');

            // Champs pour tarification simple
            $table->decimal('montant_base', 10, 2)->nullable()->after('prix_base'); // Montant de base
            $table->decimal('pourcentage_prestation', 5, 2)->nullable()->after('montant_base'); // % de prestation

            // Champs pour tarification groupage
            $table->decimal('prix_entrepot', 10, 2)->nullable()->after('pourcentage_prestation'); // Prix livraison entrepôt
            $table->decimal('supplement_domicile_groupage', 10, 2)->nullable()->after('prix_entrepot'); // Supplément livraison domicile en groupage

            // Poids minimum et maximum pour ce tarif
            $table->decimal('poids_min_kg', 8, 2)->nullable()->after('poids_max_kg');

            // Contraintes de clés étrangères
            $table->foreign('zone_depart_id')->references('id')->on('zones')->onDelete('set null');
            $table->foreign('zone_arrivee_id')->references('id')->on('zones')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tarifs', function (Blueprint $table) {
            $table->dropForeign(['zone_depart_id']);
            $table->dropForeign(['zone_arrivee_id']);
            $table->dropColumn([
                'zone_depart_id',
                'zone_arrivee_id',
                'montant_base',
                'pourcentage_prestation',
                'prix_entrepot',
                'supplement_domicile_groupage',
                'poids_min_kg'
            ]);
        });
    }
};
