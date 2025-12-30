<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expeditions', function (Blueprint $table) {
            // Remplacer les champs d'expéditeur et destinataire par des foreign keys vers contacts
            $table->uuid('expediteur_contact_id')->nullable()->after('user_id');
            $table->uuid('destinataire_contact_id')->nullable()->after('expediteur_contact_id');

            // Garder les anciens champs pour la transition (pourront être supprimés plus tard)
            // Les champs existants: nom_expediteur, adresse_expediteur, telephone_expediteur, etc.
            // et: nom_destinataire, adresse_destinataire, telephone_destinataire, etc.

            // Index pour les nouvelles relations
            $table->index('expediteur_contact_id');
            $table->index('destinataire_contact_id');

            // Foreign keys
            $table->foreign('expediteur_contact_id')->references('id')->on('contacts_expedition')->onDelete('set null');
            $table->foreign('destinataire_contact_id')->references('id')->on('contacts_expedition')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expeditions', function (Blueprint $table) {
            // Supprimer les foreign keys et les nouveaux champs
            $table->dropForeign(['expediteur_contact_id']);
            $table->dropForeign(['destinataire_contact_id']);
            $table->dropIndex(['expediteur_contact_id']);
            $table->dropIndex(['destinataire_contact_id']);
            $table->dropColumn(['expediteur_contact_id', 'destinataire_contact_id']);
        });
    }
};
