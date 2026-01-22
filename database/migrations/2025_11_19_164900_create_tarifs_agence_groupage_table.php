<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\TypeExpedition;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tarifs_agence_groupage', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Référence obligatoire vers l'agence
            $table->uuid('agence_id');
            $table->uuid('category_id')->nullable();

            // Référence obligatoire vers le tarif groupage backoffice
            $table->uuid('tarif_groupage_id');
            $table->enum('type_expedition', [TypeExpedition::class]);

            // Prix personnalisés par mode (JSONB array)
            // Chaque élément: {mode, montant_base, pourcentage_prestation, montant_prestation, montant_expedition}
            $table->jsonb('prix_modes')->nullable();
            $table->string('pays')->nullable();

            // Statut
            $table->boolean('actif')->default(true);
            $table->timestamps();

            // Contraintes de clés étrangères
            $table->foreign('agence_id')->references('id')->on('agences')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('category_products')->cascadeOnDelete();
            $table->foreign('tarif_groupage_id')->references('id')->on('tarifs_groupage')->onDelete('cascade');

            // Contrainte d'unicité : une agence ne peut avoir qu'un seul tarif pour un tarif groupage backoffice donné
            $table->unique(['agence_id', 'tarif_groupage_id']);

            // Index pour optimiser les recherches
            $table->index(['agence_id', 'category_id']);
            $table->index(['agence_id', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifs_agence_groupage');
    }
};
