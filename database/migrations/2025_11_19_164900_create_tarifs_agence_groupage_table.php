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

            $table->uuid('agence_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->uuid('tarif_groupage_id')->nullable();

            $table->enum('type_expedition', array_column(TypeExpedition::cases(), 'value'));
            $table->string('mode')->nullable();
            $table->string('ligne')->nullable();
            $table->decimal('montant_base', 10, 2)->nullable();
            $table->decimal('pourcentage_prestation', 10, 2)->nullable();
            $table->decimal('montant_prestation', 10, 2)->nullable();
            $table->decimal('montant_expedition', 10, 2)->nullable();
            $table->string('pays')->nullable();

            // Statut
            $table->boolean('actif')->default(true);
            $table->timestamps();

            // Contraintes de clés étrangères
            $table->foreign('agence_id')->references('id')->on('agences')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('category_products')->nullOnDelete();
            $table->foreign('tarif_groupage_id')->references('id')->on('tarifs_groupage')->nullOnDelete();

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
