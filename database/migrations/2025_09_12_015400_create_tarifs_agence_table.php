<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tarifs_agence', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Référence obligatoire vers l'agence
            $table->uuid('agence_id');

            // Référence obligatoire vers le tarif de base
            $table->uuid('tarif_base_id');

            // Indice copié du tarif de base (pour faciliter les requêtes)
            $table->decimal('indice', 5, 1);

            // Prix personnalisés par zone (JSON array)
            // Chaque élément: {zone_destination_id, montant_base, pourcentage_prestation_agence, montant_prestation_agence, montant_expedition_agence}
            $table->json('prix_zones');

            // Statut
            $table->boolean('actif')->default(true);
            $table->timestamps();

            // Contraintes de clés étrangères
            $table->foreign('agence_id')->references('id')->on('agences')->onDelete('cascade');
            $table->foreign('tarif_base_id')->references('id')->on('tarifs_base')->onDelete('cascade');

            // Contrainte d'unicité : une agence ne peut avoir qu'un seul tarif par tarif de base
            $table->unique(['agence_id', 'tarif_base_id']);

            // Index pour optimiser les recherches
            $table->index(['agence_id', 'actif']);

            // Index pour optimiser les recherches par indice
            $table->index(['agence_id', 'indice']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarifs_agence');
    }
};
