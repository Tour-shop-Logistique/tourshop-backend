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
        Schema::table('expeditions', function (Blueprint $table) {
            // Supprimer les foreign keys et index existants
            $table->dropForeign(['expediteur_contact_id']);
            $table->dropForeign(['destinataire_contact_id']);
            $table->dropIndex(['expediteur_contact_id']);
            $table->dropIndex(['destinataire_contact_id']);
            
            // Supprimer les colonnes d'ID de contact
            $table->dropColumn(['expediteur_contact_id', 'destinataire_contact_id']);
            
            // Ajouter les colonnes JSON pour expéditeur et destinataire
            $table->json('expediteur')->after('pays_depart');
            $table->json('destinataire')->after('pays_destination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expeditions', function (Blueprint $table) {
            // Supprimer les colonnes JSON
            $table->dropColumn(['expediteur', 'destinataire']);
            
            // Restaurer les colonnes d'ID de contact
            $table->uuid('expediteur_contact_id')->nullable()->after('pays_depart');
            $table->uuid('destinataire_contact_id')->nullable()->after('expediteur_contact_id');
            
            // Restaurer les index
            $table->index('expediteur_contact_id');
            $table->index('destinataire_contact_id');
            
            // Restaurer les foreign keys
            $table->foreign('expediteur_contact_id')->references('id')->on('contacts_expedition')->onDelete('set null');
            $table->foreign('destinataire_contact_id')->references('id')->on('contacts_expedition')->onDelete('set null');
        });
    }
};
