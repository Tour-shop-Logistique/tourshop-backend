<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code_suivi')->unique();
            
            // Informations sur l'expéditeur et le destinataire
            $table->foreignUuid('expediteur_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('destinataire_id')->nullable()->constrained('users')->onDelete('set null'); // Destinataire utilisateur
            $table->string('destinataire_nom')->nullable(); // Destinataire externe
            $table->string('destinataire_telephone')->nullable(); // Destinataire externe
            $table->text('adresse_destinataire');
            
            // Caractéristiques du colis
            $table->string('description');
            $table->decimal('poids', 8, 2);
            $table->string('photo_colis')->nullable();
            $table->decimal('valeur_declaree', 10, 2)->nullable();
            
            // Coordonnées géographiques des adresses
            $table->text('adresse_enlevement');
            $table->decimal('lat_enlevement', 10, 8);
            $table->decimal('lng_enlevement', 11, 8);
            $table->decimal('lat_livraison', 10, 8);
            $table->decimal('lng_livraison', 11, 8);
            
            // Assignation à une agence et un livreur
            $table->foreignUuid('agence_id')->nullable()->constrained('agences');
            $table->foreignUuid('livreur_id')->nullable()->constrained('users');
            
            // Suivi du statut de livraison
            $table->enum('status', [
                'en_attente', 'valide', 'en_enlevement', 'recupere',
                'en_transit', 'en_agence', 'en_livraison', 'livre', 'echec', 'annule'
            ])->default('en_attente');
            
            // Informations tarifaires
            $table->decimal('prix_total', 10, 2);
            $table->decimal('commission_livreur', 10, 2)->nullable();
            $table->decimal('commission_agence', 10, 2)->nullable();
            
            // Options de service
            $table->boolean('enlevement_domicile')->default(false);
            $table->boolean('livraison_express')->default(false);
            $table->boolean('paiement_livraison')->default(false);
            
            // Instructions pour l'enlèvement et la livraison
            $table->text('instructions_enlevement')->nullable();
            $table->text('instructions_livraison')->nullable();
            $table->text('notes_livreur')->nullable();
            
            // Preuves de livraison et signature
            $table->string('photo_livraison')->nullable();
            $table->text('signature_destinataire')->nullable();
            $table->timestamp('date_livraison')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colis');
    }
};