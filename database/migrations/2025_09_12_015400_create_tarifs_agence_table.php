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
        Schema::create('tarifs_agence_simple', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Référence obligatoire vers l'agence
            $table->uuid('agence_id');

            // Référence obligatoire vers le tarif simple
            $table->uuid('tarif_simple_id');

            // Indice copié du tarif simple (pour faciliter les requêtes)
            $table->decimal('indice', 5, 1);

            // Prix personnalisés
            $table->string('zone_destination_id');
            $table->decimal('montant_base', 12, 2);
            $table->decimal('pourcentage_prestation', 5, 2)->nullable();
            $table->decimal('montant_prestation', 12, 2)->nullable();
            $table->decimal('montant_expedition', 12, 2)->nullable();

            // Statut
            $table->boolean('actif')->default(true);
            $table->timestamps();

            // Contraintes de clés étrangères
            $table->foreign('agence_id')->references('id')->on('agences')->onDelete('cascade');
            $table->foreign('tarif_simple_id')->references('id')->on('tarifs_simple')->onDelete('cascade');

            // Contrainte d'unicité : une agence ne peut avoir qu'un seul tarif par tarif simple
            $table->unique(['agence_id', 'tarif_simple_id']);

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
        Schema::dropIfExists('tarifs_agence_simple');
    }
};
