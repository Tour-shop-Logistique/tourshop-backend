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
        Schema::create('tarifs_base', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Définition du tarif (indice + mode + type)
            $table->decimal('indice', 5, 1); // Ex: 1.0, 1.5, 2.0, etc.
            $table->enum('mode_expedition', ['simple', 'groupage']);
            $table->string('type_colis')->nullable(); // Requis pour groupage, null pour simple

            // Prix par zone (JSON array)
            // Chaque élément: {zone_destination_id, montant_base, pourcentage_prestation_base, montant_prestation_base, montant_expedition_base}
            $table->json('prix_zones');

            // Statut
            $table->boolean('actif')->default(true);

            $table->timestamps();

            // Index pour optimiser les recherches par indice/mode/type
            $table->index(['indice', 'mode_expedition', 'type_colis']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarifs_base');
    }
};
