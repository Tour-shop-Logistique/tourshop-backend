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
        Schema::create('livreurs', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID comme clé primaire pour le livreur

            // Clé étrangère vers la table 'users' (le compte d'authentification du livreur)
            $table->foreignUuid('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Clé étrangère vers la table 'agences' (l'agence à laquelle le livreur est rattaché)
            $table->foreignUuid('agence_id')
                  ->constrained('agences')
                  ->onDelete('cascade');

            $table->string('permis_de_conduire')->nullable(); // Numéro de permis
            $table->string('type_vehicule')->nullable(); // Ex: "Moto", "Voiture", "Tricycle"
            $table->string('numero_vehicule')->nullable(); // Numéro d'immatriculation
            $table->decimal('zone_de_livraison_km', 5, 2)->nullable();
            $table->enum('statut', ['disponible', 'en_service', 'en_pause', 'hors_service'])->default('disponible');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livreurs');
    }
};