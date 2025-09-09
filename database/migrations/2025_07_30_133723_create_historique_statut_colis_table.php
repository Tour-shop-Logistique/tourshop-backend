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
        Schema::create('historique_statut_colis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('colis_id')->constrained('colis')->onDelete('cascade');
            $table->string('ancien_statut')->nullable(); // Ancien statut (peut être null si c'est le premier statut)
            $table->string('nouveau_statut'); // Nouveau statut 
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null'); // Utilisateur qui a modifié le statut (livreur, backoffice, agence)
            $table->string('latitude')->nullable(); // Position lors du changement de statut 
            $table->string('longitude')->nullable(); // Position lors du changement de statut 
            $table->text('notes')->nullable(); // Notes additionnelles (ex: raison de l'échec) 
            $table->timestamps(); // date_changement par created_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_statut_colis');
    }
};