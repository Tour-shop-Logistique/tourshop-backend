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
        Schema::create('contacts_expedition', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique(); // Référence unique du contact
            $table->enum('type_contact', ['expediteur', 'destinataire']);

            // Informations personnelles
            $table->string('nom_prenom');
            $table->string('societe')->nullable();
            // Coordonnées
            $table->string('telephone');
            $table->string('email')->nullable();
            $table->string('pays');
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('etat')->nullable();
            $table->string('quartier')->nullable();
            $table->string('code_postal')->nullable();
            // Timestamps
            $table->timestamps();
            // Index
            $table->index(['type_contact']);
            $table->index(['pays', 'ville']);
            $table->index(['telephone']);
            $table->index(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts_expedition');
    }
};
